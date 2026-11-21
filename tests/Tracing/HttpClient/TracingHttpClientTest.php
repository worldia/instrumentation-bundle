<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Tracing\HttpClient;

use Instrumentation\Semantics\Url\UrlSanitizer;
use Instrumentation\Tracing\HttpClient\TracingHttpClient;
use Instrumentation\Tracing\Tracing;
use OpenTelemetry\API\Trace\NoopTracerProvider;
use OpenTelemetry\SDK\Trace\ImmutableSpan;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class TracingHttpClientTest extends TestCase
{
    private \ArrayObject $spans;

    protected function setUp(): void
    {
        $this->spans = new \ArrayObject();
        Tracing::setProvider(new TracerProvider(new SimpleSpanProcessor(new InMemoryExporter($this->spans))));
    }

    protected function tearDown(): void
    {
        Tracing::setProvider(new NoopTracerProvider());
    }

    public function testTheUrlAsSentKeepsNoCredentials(): void
    {
        $client = new TracingHttpClient(new MockHttpClient(new MockResponse('{}')));

        $client->request('GET', 'https://login:s3cret@api.example.com/search', ['query' => ['key' => '12345-abc', 'q' => 'kyoto']])->getContent();

        // Set once the response starts, from the URL as sent (its query options merged in): the userinfo redaction
        // used to be lost there, and the query was never redacted.
        $this->assertSame('https://REDACTED:REDACTED@api.example.com/search?key=REDACTED&q=kyoto', $this->urlFull());
    }

    public function testARequestRedactsItsOwnParametersToo(): void
    {
        $client = new TracingHttpClient(new MockHttpClient([new MockResponse('{}'), new MockResponse('{}')]));

        $client->request('GET', 'https://api.example.com/search', ['query' => ['appid' => 'abc', 'q' => 'kyoto'], 'extra' => ['redacted_query_parameters' => ['appid']]])->getContent();
        $this->assertSame('https://api.example.com/search?appid=REDACTED&q=kyoto', $this->urlFull());

        $client->request('GET', 'https://api.example.com/search', ['query' => ['appid' => 'abc']])->getContent();
        $this->assertSame('https://api.example.com/search?appid=abc', $this->urlFull(1), 'a request\'s own parameters are its alone');
    }

    public function testTheConfiguredParametersSurviveWithOptions(): void
    {
        $client = (new TracingHttpClient(new MockHttpClient(new MockResponse('{}')), urlSanitizer: new UrlSanitizer(['appid'])))
            ->withOptions(['base_uri' => 'https://api.example.com']);

        $client->request('GET', '/search', ['query' => ['appid' => 'abc']])->getContent();

        $this->assertSame('https://api.example.com/search?appid=REDACTED', $this->urlFull());
    }

    public function testAnIdleTickOfTheStreamDoesNotEndTheSpanOfAResponseStillComing(): void
    {
        // An empty string in a mock body is an idle chunk: the loop's poll tick, with the response not yet complete.
        $body = static function (): \Generator {
            yield '';
            yield '{"offers":[]}';
        };
        $client = new TracingHttpClient(new MockHttpClient(new MockResponse($body(), ['http_code' => 200])));
        $response = $client->request('POST', 'https://api.example.com/search');

        $endedAtTick = null;
        foreach ($client->stream($response, 0.01) as $chunk) {
            if ($chunk->isTimeout()) {
                $endedAtTick = \count($this->spans);
            }
        }

        $this->assertSame(0, $endedAtTick, 'the poll tick of the loop is not the end of the call');

        $response->cancel();
        $this->assertCount(1, $this->spans, 'a response given up on ends its span');
    }

    public function testATransportErrorOfTheStreamEndsTheSpan(): void
    {
        $client = new TracingHttpClient(new MockHttpClient(new MockResponse('', ['error' => 'connection refused'])));
        $response = $client->request('GET', 'https://api.example.com/search');

        try {
            foreach ($client->stream($response, 0.01) as $chunk) {
                $chunk->isLast();
            }
            $this->fail('the transport error is not swallowed');
        } catch (TransportExceptionInterface) {
            $this->assertCount(1, $this->spans);
        }
    }

    private function urlFull(int $index = 0): mixed
    {
        $span = $this->spans[$index] ?? null;
        $this->assertInstanceOf(ImmutableSpan::class, $span);

        return $span->getAttributes()->get('url.full');
    }
}
