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
     * @param array{trace:array<string>,span:array<string>,sampled:array<string>,operation:array<string>} $map
     */
    public function __construct(private array $map)
    {
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

        $this->setRecordKey($record, $this->map['trace'], $spanContext->getTraceId());
        $this->setRecordKey($record, $this->map['span'], $spanContext->getSpanId());
        $this->setRecordKey($record, $this->map['sampled'], $spanContext->isSampled());

        if ($span instanceof Span) {
            $this->setRecordKey($record, $this->map['operation'], $span->getName());
        }

        return $record;
    }

    /**
     * @param array<string,mixed> $record
     * @param array<string>       $keys
     * @param string|bool|int     $value
     */
    private function setRecordKey(array &$record, array $keys, $value): void
    {
        $temp = &$record;
        foreach ($keys as $key) {
            $temp = &$temp[$key];
        }
        $temp = $value;
    }
}
