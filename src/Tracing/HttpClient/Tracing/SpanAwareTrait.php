<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\HttpClient\Tracing;

use OpenTelemetry\API\Trace\SpanInterface;

trait SpanAwareTrait
{
    private SpanInterface $span;

    public function getSpan(): SpanInterface
    {
        return $this->span;
    }
}
