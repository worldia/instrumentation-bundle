<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing;

class TraceUrlGenerator implements TraceUrlGeneratorInterface
{
    public function __construct(private string $url)
    {
    }

    public function getTraceUrl(string $traceId): string
    {
        return str_replace('{traceId}', $traceId, $this->url);
    }
}
