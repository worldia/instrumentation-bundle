<?php

declare(strict_types=1);

/*
 * This file is part of the platform/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Propagation\Http;

use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;

class TraceStateHeaderProvider extends AbstractTraceHeaderProvider
{
    public static function getHeaderName(): string
    {
        return TraceContextPropagator::TRACESTATE;
    }
}
