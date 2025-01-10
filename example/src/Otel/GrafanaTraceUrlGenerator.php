<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace App\Otel;

use Instrumentation\Tracing\Bridge\TraceUrlGeneratorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class GrafanaTraceUrlGenerator implements TraceUrlGeneratorInterface
{
    private string $grafanaUrl;

    public function __construct(
        #[Autowire('%service.name%')]
        private readonly string $serviceName, string|null $grafanaUrl = null)
    {
        $this->grafanaUrl = $grafanaUrl ?: 'http://localhost:3000';
    }

    public function getTraceUrl(string $traceId): string
    {
        $trace = [
            'datasource' => 'tempo',
            'queries' => [
                [
                    'refId' => 'A',
                    'datasource' => [
                        'type' => 'tempo',
                        'uid' => 'tempo',
                    ],
                    'queryType' => 'traceql',
                    'limit' => 20,
                    'tableType' => 'traces',
                    'query' => $traceId,
                ],
            ],
            'range' => [
                'from' => 'now-1h',
                'to' => 'now',
            ],
        ];
        $logs = [
            'datasource' => 'loki',
            'queries' => [
                [
                    'expr' => \sprintf('{service_name="%s"} | trace_id="%s"', $this->serviceName, $traceId),
                    'refId' => 'A',
                ],
            ],
            'range' => [
                'from' => 'now-1h',
                'to' => 'now',
            ],
        ];

        return \sprintf('%s/explore?schemaVersion=1&orgId=1&panes=%s', $this->grafanaUrl, urlencode(json_encode([
            'trace' => $trace,
            'logs' => $logs,
        ])));
    }
}
