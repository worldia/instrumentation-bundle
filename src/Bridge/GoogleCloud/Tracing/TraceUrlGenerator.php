<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Bridge\GoogleCloud\Tracing;

use Instrumentation\Tracing\TraceUrlGeneratorInterface;

class TraceUrlGenerator implements TraceUrlGeneratorInterface
{
    public function __construct(private string $project)
    {
    }

    public function getTraceUrl(string $traceId): string
    {
        return sprintf('https://console.cloud.google.com/traces/list?tid=%s&project=%s', $traceId, $this->project);
    }

    public function getLogsUrl(string $traceId): string
    {
        $identifier = sprintf('projects/%s/traces/%s', $this->project, $traceId);
        $query = urlencode(sprintf('trace="%s"', $identifier));

        return sprintf('https://console.cloud.google.com/logs/query;query=%s?project=%s', $query, $this->project);
    }
}
