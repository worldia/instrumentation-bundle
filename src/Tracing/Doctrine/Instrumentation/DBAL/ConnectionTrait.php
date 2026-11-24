<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Doctrine\Instrumentation\DBAL;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Instrumentation\Tracing\Bridge\MainSpanContextInterface;
use Instrumentation\Tracing\TracerAwareTrait;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\ContextKeys;

/**
 * Holds the tracing logic shared between the DBAL 3 and DBAL 4 connection
 * decorators. Both decorators extend {@see \Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware}
 * (which carries the version-specific method signatures) and only differ in the
 * return type of the transaction methods, so the actual instrumentation lives here.
 */
trait ConnectionTrait
{
    use TracerAwareTrait;

    private const OP_CONN_PREPARE = 'db.connection.prepare';
    private const OP_CONN_QUERY = 'db.connection.query';
    private const OP_CONN_EXEC = 'db.connection.exec';
    private const OP_CONN_BEGIN_TRANSACTION = 'db.connection.begin_transaction';
    private const OP_TRANSACTION_COMMIT = 'db.transaction.commit';
    private const OP_TRANSACTION_ROLLBACK = 'db.transaction.rollback';

    private ContextInterface $doctrineSpanContext;
    /** Span id of the main span (request, command, message) the `db.orm` span was created under. */
    private string|null $doctrineSpanMainSpanId = null;

    /**
     * @param array<string,string> $attributes
     */
    public function __construct(TracerProviderInterface $tracerProvider, ConnectionInterface $decorated, private MainSpanContextInterface $mainSpanContext, private array $attributes, private bool $logQueries, private QueryTally|null $tally = null)
    {
        parent::__construct($decorated);

        $this->tracerProvider = $tracerProvider;
    }

    public function prepare(string $sql): StatementInterface
    {
        $statement = $this->trace(self::OP_CONN_PREPARE, $sql, fn (): StatementInterface => parent::prepare($sql));

        // The parent context is asked for when the statement runs, not fixed now: a statement can outlive the main span it
        // was prepared under, and with a minimum duration the `db.orm` span is only made for the first statement to need it.
        return new Statement($this->tracerProvider, $this->doctrineContext(...), $statement, $sql, $this->attributes, $this->logQueries, $this->tally);
    }

    public function query(string $sql): Result
    {
        return $this->trace(self::OP_CONN_QUERY, $sql, fn (): Result => parent::query($sql));
    }

    public function exec(string $sql): int
    {
        return $this->trace(self::OP_CONN_EXEC, $sql, fn () => parent::exec($sql));
    }

    protected function traceBeginTransaction(): mixed
    {
        return $this->trace(self::OP_CONN_BEGIN_TRANSACTION, 'BEGIN TRANSACTION', fn () => parent::beginTransaction());
    }

    protected function traceCommit(): mixed
    {
        return $this->trace(self::OP_TRANSACTION_COMMIT, 'COMMIT', fn () => parent::commit());
    }

    protected function traceRollBack(): mixed
    {
        return $this->trace(self::OP_TRANSACTION_ROLLBACK, 'ROLLBACK', fn () => parent::rollBack());
    }

    protected function trace(string $operation, string $sql, callable $callback): mixed
    {
        if (true === $this->tally?->bounded()) {
            return $this->traceBounded($operation, $sql, $callback);
        }

        $this->ensureMainSpan();

        $capturing = true === $this->tally?->capturing() && (self::OP_CONN_QUERY === $operation || self::OP_CONN_EXEC === $operation);
        $startedAt = $capturing ? hrtime(true) : 0;
        $span = $this->getTracer()
            ->spanBuilder($operation) // @phpstan-ignore-line
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setParent($this->doctrineSpanContext)
            ->setAttributes($this->attributes)
            ->startSpan();

        if ($this->logQueries) {
            $span->addEvent($sql);
        }

        try {
            return $callback();
        } finally {
            $span->end();
            if ($capturing) {
                $this->tally->captureStatement($sql, $this->attributes, hrtime(true) - $startedAt, false);
            }
        }
    }

    /**
     * With a minimum duration: the statement is timed, counted on the main span, and only made a span, started when it
     * did, when it was slow enough. The cheap ones leave nothing in the trace but their share of the totals.
     */
    private function traceBounded(string $operation, string $sql, callable $callback): mixed
    {
        \assert(null !== $this->tally);
        $startedAtNs = (int) (microtime(true) * 1e9);
        $startedAt = hrtime(true);

        try {
            return $callback();
        } finally {
            $durationNs = hrtime(true) - $startedAt;
            $counted = self::OP_CONN_QUERY === $operation || self::OP_CONN_EXEC === $operation;
            $spanned = $this->tally->worthASpan($durationNs);

            if ($counted) {
                $this->tally->record($durationNs, $spanned);
                $this->tally->captureStatement($sql, $this->attributes, $durationNs, false);
            }
            if ($spanned) {
                $span = $this->getTracer()
                    ->spanBuilder($operation) // @phpstan-ignore-line
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setParent($this->doctrineContext())
                    ->setAttributes($this->attributes)
                    ->setStartTimestamp($startedAtNs)
                    ->startSpan();
                if ($this->logQueries) {
                    $span->addEvent($sql);
                }
                $span->end($startedAtNs + $durationNs);
            }
        }
    }

    /** The context query spans hang under: this main span's `db.orm` span, made if it is not yet. */
    private function doctrineContext(): ContextInterface
    {
        $this->ensureMainSpan();

        return $this->doctrineSpanContext;
    }

    /**
     * Creates the `db.orm` span the query spans hang under, once per main span. A connection outlives a request
     * in a long-lived worker (FrankenPHP, messenger:consume): remembering the span for good would parent the
     * queries of every later request or message to the first one, merging their traces into it.
     */
    private function ensureMainSpan(): void
    {
        $mainSpan = $this->mainSpanContext->getMainSpan();
        $mainSpanId = $mainSpan->getContext()->getSpanId();

        if ($this->doctrineSpanMainSpanId === $mainSpanId) {
            return;
        }

        $parentContext = Context::getCurrent()->with(ContextKeys::span(), $mainSpan);

        $doctrineSpan = $this->getTracer()->spanBuilder('db.orm')->setParent($parentContext)->setSpanKind(SpanKind::KIND_CLIENT)->setAttributes($this->attributes)->startSpan();
        $this->doctrineSpanContext = Context::getCurrent()->with(ContextKeys::span(), $doctrineSpan);
        $doctrineSpan->end();

        $this->doctrineSpanMainSpanId = $mainSpanId;
    }
}
