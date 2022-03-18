<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Logging\Processor;

use OpenTelemetry\SDK\Trace\Span;

class TraceContextProcessor
{
    /**
     * @param array<mixed> $record
     *
     * @return array<mixed>
     */
    public function __invoke(array $record): array
    {
        $span = Span::getCurrent();
        $spanContext = $span->getContext();

        $record['extra']['traceId'] = $spanContext->getTraceId();
        $record['extra']['spanId'] = $spanContext->getSpanId();
        $record['extra']['traceSampled'] = $spanContext->isSampled();

        if ($span instanceof Span) {
            $record['extra']['traceOperation'] = $span->getName();
        }

        return $record;
    }
}
