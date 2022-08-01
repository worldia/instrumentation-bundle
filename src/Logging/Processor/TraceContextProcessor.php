<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Logging\Processor;

use Monolog\LogRecord;
use OpenTelemetry\SDK\Trace\Span;

class TraceContextProcessor
{
    /**
     * @param array{trace:array<string>,span:array<string>,sampled:array<string>,operation:array<string>} $map
     */
    public function __construct(private array $map)
    {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $span = Span::getCurrent();
        $spanContext = $span->getContext();

        if (!$spanContext->isValid()) {
            return $record;
        }

        $context = $record->context;
        $context[$this->map['trace']] = $spanContext->getTraceId();
        $context[$this->map['span']] = $spanContext->getSpanId();
        $context[$this->map['sampled']] = $spanContext->isSampled();

        if ($span instanceof Span) {
            $context[$this->map['operation']] = $span->getName();
        }

        $record = $record->with(
            context: $context,
        );

        return $record;
    }
}
