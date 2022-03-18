<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Logging\Processor;

use OpenTelemetry\SDK\Trace\Span;

class TraceContextProcessor
{
    public function __construct(
        private string $traceIdKey = 'trace',
        private string $spanIdKey = 'spanId',
        private string $sampledKey = 'traceSampled',
        private string $operationKey = 'traceOperation'
    ) {
    }

    /**
     * @param array<mixed> $record
     *
     * @return array<mixed>
     */
    public function __invoke(array $record): array
    {
        $span = Span::getCurrent();
        $spanContext = $span->getContext();

        $record['context'][$this->traceIdKey] = $spanContext->getTraceId();
        $record['context'][$this->spanIdKey] = $spanContext->getSpanId();
        $record['context'][$this->sampledKey] = $spanContext->isSampled();

        if ($span instanceof Span) {
            $record['context'][$this->operationKey] = $span->getName();
        }

        return $record;
    }
}
