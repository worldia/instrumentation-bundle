<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\HttpClient\Tracing;

use OpenTelemetry\API\Trace\SpanInterface;

interface SpanAwareInterface
{
    public function getSpan(): SpanInterface;
}
