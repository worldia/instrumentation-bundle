<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Instrumentation\Doctrine\DBAL;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;
use Instrumentation\Tracing\Instrumentation\MainSpanContextInterface;
use Instrumentation\Tracing\Instrumentation\TracerAwareTrait;
use OpenTelemetry\API\Trace\SpanContextKey;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\Context;

final class Connection implements ServerInfoAwareConnection
{
    use TracerAwareTrait;

    private const OP_CONN_PREPARE = 'db.connection.prepare';
    private const OP_CONN_QUERY = 'db.connection.query';
    private const OP_CONN_EXEC = 'db.connection.exec';
    private const OP_CONN_BEGIN_TRANSACTION = 'db.connection.begin_transaction';
    private const OP_TRANSACTION_COMMIT = 'db.transaction.commit';
    private const OP_TRANSACTION_ROLLBACK = 'db.transaction.rollback';

    private Context $doctrineSpanContext;
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

        return new Statement($this->tracerProvider, $this->doctrineSpanContext, $statement, $sql, $this->attributes);
    }

    public function query(string $sql): Result
    {
        return $this->trace(self::OP_CONN_QUERY, $sql, fn (): Result => $this->decorated->query($sql));
    }

    /**
     * @return mixed
     */
    public function quote($value, $type = ParameterType::STRING)
    {
        return $this->decorated->quote($value, $type);
    }

    public function exec(string $sql): int
    {
        return $this->trace(self::OP_CONN_EXEC, $sql, fn (): int => $this->decorated->exec($sql));
    }

    /**
     * @return string|int|false
     */
    public function lastInsertId($name = null)
    {
        return $this->decorated->lastInsertId($name);
    }

    public function beginTransaction(): bool
    {
        return $this->trace(self::OP_CONN_BEGIN_TRANSACTION, 'BEGIN TRANSACTION', fn (): bool => $this->decorated->beginTransaction());
    }

    public function commit(): bool
    {
        return $this->trace(self::OP_TRANSACTION_COMMIT, 'COMMIT', fn (): bool => $this->decorated->commit());
    }

    public function rollBack(): bool
    {
        return $this->trace(self::OP_TRANSACTION_ROLLBACK, 'ROLLBACK', fn (): bool => $this->decorated->rollBack());
    }

    public function getServerVersion(): string
    {
        if ($this->decorated instanceof ServerInfoAwareConnection) {
            return $this->decorated->getServerVersion();
        }

        return 'unknown';
    }

    /**
     * @return \PDO|object|resource
     */
    public function getNativeConnection()
    {
        return $this->decorated->getNativeConnection();
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
        $parentContext = Context::getCurrent()->with(SpanContextKey::instance(), $mainSpan);

        $doctrineSpan = $this->getTracer()->spanBuilder('db.orm')->setParent($parentContext)->setSpanKind(SpanKind::KIND_CLIENT)->setAttributes($this->attributes)->startSpan();
        $this->doctrineSpanContext = Context::getCurrent()->with(SpanContextKey::instance(), $doctrineSpan);
        $doctrineSpan->end();

        $this->createdDoctrineSpanContext = true;
    }
}
