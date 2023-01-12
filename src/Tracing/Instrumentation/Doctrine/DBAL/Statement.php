<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Instrumentation\Doctrine\DBAL;

use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement as DoctrineStatement;
use Doctrine\DBAL\ParameterType;
use Instrumentation\Tracing\Instrumentation\TracerAwareTrait;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\ContextInterface;

class Statement implements DoctrineStatement
{
    use TracerAwareTrait;

    private const OP_STMT_EXECUTE = 'db.statement.execute';

    /**
     * @param array<string,string> $attributes
     */
    public function __construct(protected TracerProviderInterface $tracerProvider, private ContextInterface $parentContext, private DoctrineStatement $decoratedStatement, private string $sqlQuery, private array $attributes)
    {
    }

    public function bindValue($param, $value, $type = ParameterType::STRING): bool
    {
        return $this->decoratedStatement->bindValue($param, $value, $type);
    }

    public function bindParam($param, &$variable, $type = ParameterType::STRING, $length = null): bool
    {
        return $this->decoratedStatement->bindParam($param, $variable, $type, ...\array_slice(\func_get_args(), 3));
    }

    public function execute($params = null): Result
    {
        $span = $this->getTracer()
            ->spanBuilder(self::OP_STMT_EXECUTE)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setParent($this->parentContext)
            ->setAttributes($this->attributes)
            ->startSpan()
            ->addEvent($this->sqlQuery);

        try {
            return $this->decoratedStatement->execute($params);
        } finally {
            $span->end();
        }
    }
}
