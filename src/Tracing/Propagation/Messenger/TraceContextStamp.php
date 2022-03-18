<?php

declare(strict_types=1);

/*
 * This file is part of the platform/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Propagation\Messenger;

use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use Symfony\Component\Messenger\Stamp\StampInterface;

final class TraceContextStamp implements StampInterface
{
    private string $traceParent;
    private ?string $traceState;

    public function __construct()
    {
        $traceContext = [];
        TraceContextPropagator::getInstance()->inject($traceContext);

        $this->traceParent = $traceContext[TraceContextPropagator::TRACEPARENT];
        $this->traceState = $traceContext[TraceContextPropagator::TRACESTATE] ?? null;
    }

    public function getTraceParent(): string
    {
        return $this->traceParent;
    }

    public function getTraceState(): ?string
    {
        return $this->traceState;
    }
}
