<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Bridge\Jaeger\Tracing;

use Instrumentation\Tracing\TraceUrlGeneratorInterface;

class TraceUrlGenerator implements TraceUrlGeneratorInterface
{
    public function __construct(private string $baseUrl)
    {
    }

    public function getTraceUrl(string $traceId): string
    {
        return sprintf('%s/trace/%s', rtrim($this->baseUrl, '/'), $traceId);
    }

    public function getLogsUrl(string $traceId): string
    {
        return sprintf('%s/trace/%s', rtrim($this->baseUrl, '/'), $traceId);
    }
}
