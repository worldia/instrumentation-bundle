<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Bridge\Sentry\Tracing;

use OpenTelemetry\SDK\Trace\SpanConverterInterface;
use OpenTelemetry\SDK\Trace\SpanDataInterface;

class SpanConverter implements SpanConverterInterface
{
    /**
     * @param iterable<SpanDataInterface> $spans
     *
     * @return array<mixed>
     */
    public function convert(iterable $spans): array
    {
        $aggregate = [];
        foreach ($spans as $span) {
            $aggregate[] = $this->convertSpan($span);
        }

        return $aggregate;
    }

    /**
     * @return array<mixed>
     */
    private function convertSpan(SpanDataInterface $span): array
    {
        $events = $span->getEvents();
        $event = reset($events);

        $result = [
            'trace_id' => (string) $span->getTraceId(),
            'span_id' => (string) $span->getSpanId(),
            'parent_span_id' => $span->getParentContext()->isValid() ? $span->getParentSpanId() : null,
            'start_timestamp' => Util::nanosToSeconds($span->getStartEpochNanos()),
            'timestamp' => Util::nanosToSeconds($span->getEndEpochNanos()),
            'op' => $span->getName(),
            'description' => $event ? $event->getName() : null,
            'tags' => $span->getAttributes()->toArray(),
            'status' => Util::toSentrySpanStatus($span->getStatus()->getCode()),
        ];

        if (null !== $span->getStatus()) {
            $result['status'] = Util::toSentrySpanStatus($span->getStatus()->getCode());
        }

        return array_filter($result);
    }
}
