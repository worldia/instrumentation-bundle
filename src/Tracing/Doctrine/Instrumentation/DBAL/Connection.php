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

final class Connection implements ConnectionInterface
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
    public function __construct(protected TracerProviderInterface $tracerProvider, protected ConnectionInterface $decorated, private MainSpanContextInterface $mainSpanContext, private array $attributes, private bool $logQueries)
    {
    }

    public function prepare(string $sql): StatementInterface
    {
        $statement = $this->trace(self::OP_CONN_PREPARE, $sql, fn (): StatementInterface => $this->decorated->prepare($sql));

        return new Statement($this->tracerProvider, $this->doctrineSpanContext, $statement, $sql, $this->attributes, $this->logQueries);
    }

    public function query(string $sql): Result
    {
        return $this->trace(self::OP_CONN_QUERY, $sql, fn (): Result => $this->decorated->query($sql));
    }

    public function quote(string $value): string
    {
        return $this->decorated->quote($value);
    }

    public function exec(string $sql): int|string
    {
        return $this->trace(self::OP_CONN_EXEC, $sql, fn (): int|string => $this->decorated->exec($sql));
    }

    public function lastInsertId(): string|int
    {
        return $this->decorated->lastInsertId();
    }

    public function beginTransaction(): void
    {
        $this->trace(self::OP_CONN_BEGIN_TRANSACTION, 'BEGIN TRANSACTION', fn () => $this->decorated->beginTransaction());
    }

    public function commit(): void
    {
        $this->trace(self::OP_TRANSACTION_COMMIT, 'COMMIT', fn () => $this->decorated->commit());
    }

    public function rollBack(): void
    {
        $this->trace(self::OP_TRANSACTION_ROLLBACK, 'ROLLBACK', fn () => $this->decorated->rollBack());
    }

    /**
     * @return object|resource
     */
    public function getNativeConnection()
    {
        return $this->decorated->getNativeConnection();
    }

    public function getServerVersion(): string
    {
        return $this->decorated->getServerVersion();
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
