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
    private bool $createdDoctrineSpanContext = false;

    /**
     * @param array<string,string> $attributes
     */
    public function __construct(TracerProviderInterface $tracerProvider, ConnectionInterface $decorated, private MainSpanContextInterface $mainSpanContext, private array $attributes, private bool $logQueries)
    {
        parent::__construct($decorated);

        $this->tracerProvider = $tracerProvider;
    }

    public function prepare(string $sql): StatementInterface
    {
        $statement = $this->trace(self::OP_CONN_PREPARE, $sql, fn (): StatementInterface => parent::prepare($sql));

        return new Statement($this->tracerProvider, $this->doctrineSpanContext, $statement, $sql, $this->attributes, $this->logQueries);
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
        $this->ensureMainSpan();

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
        }
    }

    private function ensureMainSpan(): void
    {
        if ($this->createdDoctrineSpanContext) {
            return;
        }

        $mainSpan = $this->mainSpanContext->getMainSpan();
        $parentContext = Context::getCurrent()->with(ContextKeys::span(), $mainSpan);

        $doctrineSpan = $this->getTracer()->spanBuilder('db.orm')->setParent($parentContext)->setSpanKind(SpanKind::KIND_CLIENT)->setAttributes($this->attributes)->startSpan();
        $this->doctrineSpanContext = Context::getCurrent()->with(ContextKeys::span(), $doctrineSpan);
        $doctrineSpan->end();

        $this->createdDoctrineSpanContext = true;
    }
}
