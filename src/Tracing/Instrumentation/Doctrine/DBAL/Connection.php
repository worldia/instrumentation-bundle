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
use Instrumentation\Tracing\Instrumentation\TracerAwareTrait;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\Context;

final class Connection implements ServerInfoAwareConnection
{
    use TracerAwareTrait;

    private ?SpanInterface $mainSpan = null;
    private Context $mainSpanContext;

    /**
     * @param array<string,string> $attributes
     */
    public function __construct(protected TracerProviderInterface $tracerProvider, protected ConnectionInterface $decorated, private array $attributes)
    {
    }

    public function prepare(string $sql): StatementInterface
    {
        $statement = $this->trace('sql.prepare '.$sql, fn (): StatementInterface => $this->decorated->prepare($sql));

        return new Statement($this->tracerProvider, $this->mainSpanContext, $statement, $sql, $this->attributes);
    }

    public function query(string $sql): Result
    {
        return $this->trace('sql.query '.$sql, fn (): Result => $this->decorated->query($sql));
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
        return $this->trace('sql.exec '.$sql, fn (): int => $this->decorated->exec($sql));
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
        return $this->trace('sql.begin_transaction BEGIN TRANSACTION', fn (): bool => $this->decorated->beginTransaction());
    }

    public function commit(): bool
    {
        return $this->trace('sql.commit COMMIT', fn (): bool => $this->decorated->commit());
    }

    public function rollBack(): bool
    {
        return $this->trace('sql.rollback ROLLBACK', fn (): bool => $this->decorated->rollBack());
    }

    public function getServerVersion(): string
    {
        if ($this->decorated instanceof ServerInfoAwareConnection) {
            return $this->decorated->getServerVersion();
        }

        return 'unkown';
    }

    protected function trace(string $operation, callable $callable): mixed
    {
        $this->ensureMainSpan();

        return $this->traceFunction($operation, $this->attributes, \Closure::fromCallable($callable), $this->mainSpanContext, SpanKind::KIND_CLIENT); // @phpstan-ignore-line
    }

    private function ensureMainSpan(): void
    {
        if ($this->mainSpan) {
            return;
        }

        $this->mainSpan = $this->getTracer()->spanBuilder('doctrine')->setSpanKind(SpanKind::KIND_CLIENT)->setAttributes($this->attributes)->startSpan();
        $this->mainSpanContext = Context::getCurrent()->withContextValue($this->mainSpan);
        $this->mainSpan->end();
    }
}
