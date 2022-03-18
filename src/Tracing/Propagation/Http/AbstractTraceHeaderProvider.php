<?php

declare(strict_types=1);

/*
 * This file is part of the platform/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Propagation\Http;

use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;

abstract class AbstractTraceHeaderProvider implements \Stringable
{
    abstract public static function getHeaderName(): string;

    public function getHeaderValue(): string
    {
        $traceContext = [];
        TraceContextPropagator::getInstance()->inject($traceContext);

        return $traceContext[static::getHeaderName()] ?? '';
    }

    public function __toString()
    {
        return $this->getHeaderValue();
    }
}
