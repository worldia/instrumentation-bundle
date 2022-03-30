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
        private string $traceIdKey = 'context.trace',
        private string $spanIdKey = 'context.spanId',
        private string $sampledKey = 'context.traceSampled',
        private string $operationKey = 'context.traceOperation'
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

        if (!$spanContext->isValid()) {
            return $record;
        }

        $this->setRecordKey($record, $this->traceIdKey, $spanContext->getTraceId());
        $this->setRecordKey($record, $this->spanIdKey, $spanContext->getSpanId());
        $this->setRecordKey($record, $this->sampledKey, $spanContext->isSampled());

        if ($span instanceof Span) {
            $this->setRecordKey($record, $this->operationKey, $span->getName());
        }

        return $record;
    }

    /**
     * @param array<string,mixed> $record
     * @param string|bool|int     $value
     */
    private function setRecordKey(array &$record, string $key, $value): void
    {
        $keys = explode('.', $key);
        $temp = &$record;

        foreach ($keys as $key) {
            $temp = &$temp[$key];
        }
        $temp = $value;
    }
}
