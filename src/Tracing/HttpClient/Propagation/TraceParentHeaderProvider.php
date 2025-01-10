<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\HttpClient\Propagation;

use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;

class TraceParentHeaderProvider extends AbstractTraceHeaderProvider
{
    public static function getHeaderName(): string
    {
        return TraceContextPropagator::TRACEPARENT;
    }
}
