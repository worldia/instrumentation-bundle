<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Messenger\Stamp;

use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\SpanContext;
use Symfony\Component\Messenger\Stamp\StampInterface;

final class TraceContextStamp implements StampInterface
{
    private string $traceParent;
    private string|null $traceState;

    public function __construct()
    {
        $traceContext = [];
        TraceContextPropagator::getInstance()->inject($traceContext);

        $this->traceParent = $traceContext[TraceContextPropagator::TRACEPARENT] ?? SpanContext::getInvalid()->getTraceId();
        $this->traceState = $traceContext[TraceContextPropagator::TRACESTATE] ?? null;
    }

    public function getTraceParent(): string
    {
        return $this->traceParent;
    }

    public function getTraceState(): string|null
    {
        return $this->traceState;
    }
}
