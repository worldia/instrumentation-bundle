<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Doctrine\Instrumentation\DBAL;

use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement as DoctrineStatement;
use Instrumentation\Tracing\TracerAwareTrait;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\ContextInterface;

class Statement extends AbstractStatementMiddleware
{
    use TracerAwareTrait;

    private const OP_STMT_EXECUTE = 'db.statement.execute';

    /**
     * @param array<string,string> $attributes
     */
    public function __construct(TracerProviderInterface $tracerProvider, private ContextInterface $parentContext, DoctrineStatement $decoratedStatement, private string $sqlQuery, private array $attributes, private bool $logSql)
    {
        parent::__construct($decoratedStatement);

        $this->tracerProvider = $tracerProvider;
    }

    /**
     * The optional $params argument keeps backward compatibility with
     * doctrine/dbal 3, where Statement::execute() still accepts parameters.
     * It is forwarded as-is so that dbal 4 (which takes no argument) is never
     * passed one.
     *
     * @param array<int|string,mixed>|null $params
     */
    public function execute($params = null): Result
    {
        $span = $this->getTracer()
            ->spanBuilder(self::OP_STMT_EXECUTE)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setParent($this->parentContext)
            ->setAttributes($this->attributes)
            ->startSpan();

        if ($this->logSql) {
            $span->addEvent($this->sqlQuery);
        }

        try {
            return parent::execute(...\func_get_args());
        } finally {
            $span->end();
        }
    }
}
