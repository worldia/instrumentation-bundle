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
     * @param array<string,string>                          $attributes
     * @param ContextInterface|\Closure(): ContextInterface $parentContext the context the execute span hangs under, or what
     *                                                                     gives it when the statement runs (the connection's
     *                                                                     current `db.orm` span)
     */
    public function __construct(TracerProviderInterface $tracerProvider, private ContextInterface|\Closure $parentContext, DoctrineStatement $decoratedStatement, private string $sqlQuery, private array $attributes, private bool $logSql, private QueryTally|null $tally = null)
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
        if (true === $this->tally?->bounded()) {
            return $this->executeBounded(...\func_get_args());
        }

        $span = $this->getTracer()
            ->spanBuilder(self::OP_STMT_EXECUTE)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setParent($this->parentContext())
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

    /**
     * With a minimum duration: counted on the main span, a span only when slow enough, started when the statement did.
     *
     * @param array<int|string,mixed>|null $params
     */
    private function executeBounded($params = null): Result
    {
        \assert(null !== $this->tally);
        $startedAtNs = (int) (microtime(true) * 1e9);
        $startedAt = hrtime(true);

        try {
            return parent::execute(...\func_get_args());
        } finally {
            $durationNs = hrtime(true) - $startedAt;
            $spanned = $this->tally->worthASpan($durationNs);
            $this->tally->record($durationNs, $spanned);

            if ($spanned) {
                $span = $this->getTracer()
                    ->spanBuilder(self::OP_STMT_EXECUTE)
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setParent($this->parentContext())
                    ->setAttributes($this->attributes)
                    ->setStartTimestamp($startedAtNs)
                    ->startSpan();
                if ($this->logSql) {
                    $span->addEvent($this->sqlQuery);
                }
                $span->end($startedAtNs + $durationNs);
            }
        }
    }

    private function parentContext(): ContextInterface
    {
        return $this->parentContext instanceof \Closure ? ($this->parentContext)() : $this->parentContext;
    }
}
