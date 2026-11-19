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

    private function urlFull(int $index = 0): mixed
    {
        $span = $this->spans[$index] ?? null;
        $this->assertInstanceOf(ImmutableSpan::class, $span);

        return $span->getAttributes()->get('url.full');
    }
}
