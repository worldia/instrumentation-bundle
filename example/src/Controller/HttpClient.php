<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace App\Controller;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HttpClient extends AbstractController
{
    #[Route('/http')]
    public function currentTime(
        #[Autowire(service: HttpClientInterface::class)]
        HttpClientInterface $httpClient,
        #[Autowire(service: TracerProviderInterface::class)]
        TracerProviderInterface $tracerProvider,
    ): Response {
        $timezones = [
            'America/New_York',
            'Europe/Paris',
            'Europe/London',
            'Asia/Singapore',
            'Australia/Sydney',
            'Asia/Tokyo',
        ];

        $responses = [];
        $responsesMap = new \SplObjectStorage();
        foreach ($timezones as $timezone) {
            $response = $httpClient->request('GET', \sprintf('https://timeapi.io/api/time/current/zone?timeZone=%s', $timezone), ['extra' => $this->getExtraOptions()]);
            $responses[] = $response;
            $responsesMap[$response] = $timezone;
        }

        $info = [];
        foreach ($httpClient->stream($responses) as $response => $chunk) {
            if ($chunk->isLast()) {
                $info[$responsesMap[$response]] = $response->toArray();
            }
        }

        $result = [$this->getTraceLink(), '<br>'];
        $span = $tracerProvider->getTracer('app')->spanBuilder('process')->startSpan();
        foreach ($info as $timezone => $time) {
            $result[] = \sprintf('Current time is %s in timezone "%s".', $time['dateTime'], $time['timeZone']);
        }
        $span->end();

        return new Response(implode('<br>', $result));
    }

    /**
     * None of these options are required.
     */
    private function getExtraOptions(): array
    {
        return [
            'propagate' => false,
            'operation_name' => 'http.get timeapi.io-time',
            'on_request' => function (array $headers, string|null $body, SpanInterface $span): void {
                $span->setAttribute('request.headers', $headers);
            },
            'on_response' => function (array $headers, string|null $body, SpanInterface $span): void {
                $span->setAttribute('response.headers', json_encode($headers));
            },
        ];
    }
}
