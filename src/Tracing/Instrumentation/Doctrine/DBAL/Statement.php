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
use OpenTelemetry\Context\Context;

class Statement implements DoctrineStatement
{
    use TracerAwareTrait;

    /**
     * @param string               $sqlQuery
     * @param array<string,string> $attributes
     */
    public function __construct(protected TracerProviderInterface $tracerProvider, private Context $parentContext, private DoctrineStatement $decoratedStatement, private string $sqlQuery, private array $attributes)
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
        $statement = $this->decoratedStatement;

        return $this->traceFunction(
            'sql.stmt '.$this->sqlQuery,
            $this->attributes,
            function () use ($statement, $params) {
                return $statement->execute($params);
            },
            $this->parentContext,
            SpanKind::KIND_CLIENT
        );
    }
}
