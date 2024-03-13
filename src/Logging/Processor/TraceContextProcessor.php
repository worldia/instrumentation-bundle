<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Logging\Processor;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use OpenTelemetry\SDK\Trace\Span;

class TraceContextProcessor implements ProcessorInterface
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

        $record = $this->withRecordKey($record, $this->map['trace'], $spanContext->getTraceId());
        $record = $this->withRecordKey($record, $this->map['span'], $spanContext->getSpanId());
        $record = $this->withRecordKey($record, $this->map['sampled'], $spanContext->isSampled());

        if ($span instanceof Span) {
            $record = $this->withRecordKey($record, $this->map['operation'], $span->getName());
        }

        return $record;
    }

    /**
     * @param array<string>   $keys
     * @param string|bool|int $value
     */
    private function withRecordKey(LogRecord $record, array $keys, $value): LogRecord
    {
        if ([] === $keys) {
            return $record;
        }
        $first = array_shift($keys);

        $array = $record[$first];
        $temp = &$array;
        foreach ($keys as $key) {
            $temp = &$temp[$key];
        }
        $temp = $value;

        return $record->with(...[$first => $array]);
    }
}
