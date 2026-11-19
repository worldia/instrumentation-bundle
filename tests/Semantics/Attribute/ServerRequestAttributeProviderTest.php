<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Semantics\Attribute;

use Instrumentation\Semantics\Attribute\ServerRequestAttributeProvider;
use Instrumentation\Semantics\Attribute\ServerRequestAttributeProviderInterface;
use Instrumentation\Semantics\Url\UrlSanitizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class ServerRequestAttributeProviderTest extends TestCase
{
    public function testItImplementsServerRequestAttributeProviderInterface(): void
    {
        $this->assertTrue(is_a(ServerRequestAttributeProvider::class, ServerRequestAttributeProviderInterface::class, true));
    }

    public function testItSetsClientIp(): void
    {
        $attributes = (new ServerRequestAttributeProvider())->getAttributes(Request::create('/', server: ['REMOTE_ADDR' => '10.11.12.13']));

        $this->assertEquals('10.11.12.13', $attributes['client.address']);
    }

    public function testItSetsServerAddressFromHostHeader(): void
    {
        $attributes = (new ServerRequestAttributeProvider())->getAttributes(Request::create('https://www.example.com/'));

        $this->assertEquals('www.example.com', $attributes['server.address']);
    }

    public function testItSetsServerAddressFromServerNameConstructorArg(): void
    {
        $attributes = (new ServerRequestAttributeProvider('www.test.com'))->getAttributes(Request::create('https://www.example.com/'));

        $this->assertEquals('www.test.com', $attributes['server.address']);
    }

    public function testItSetsPort(): void
    {
        $attributes = (new ServerRequestAttributeProvider())->getAttributes(Request::create('https://www.example.com/'));

        $this->assertEquals('443', $attributes['server.port']);
    }

    public function testItSetsNetworkProtocolName(): void
    {
        $attributes = (new ServerRequestAttributeProvider())->getAttributes(Request::create('/', server: ['SERVER_PROTOCOL' => 'HTTP/1.1']));

        $this->assertEquals('1.1', $attributes['network.protocol.version']);
        $this->assertEquals('http', $attributes['network.protocol.name']);
    }

    public function testItSetsUrlScheme(): void
    {
        $attributes = (new ServerRequestAttributeProvider())->getAttributes(Request::create('https://www.example.com/'));

        $this->assertEquals('https', $attributes['url.scheme']);
    }

    public function testItSetsUrlPath(): void
    {
        $attributes = (new ServerRequestAttributeProvider())->getAttributes(Request::create('/some/path'));

        $this->assertEquals('/some/path', $attributes['url.path']);
    }

    public function testItSetsUrlQuery(): void
    {
        $attributes = (new ServerRequestAttributeProvider())->getAttributes(Request::create('/?foo=bar&bar=baz'));

        // Normalized by Symfony: its parameters sorted.
        $this->assertEquals('bar=baz&foo=bar', $attributes['url.query']);
    }

    public function testItSetsHttpRequestMethod(): void
    {
        $attributes = (new ServerRequestAttributeProvider())->getAttributes(Request::create('/', 'PATCH'));

        $this->assertEquals('PATCH', $attributes['http.request.method']);
    }

    public function testItSetsHttpRoute(): void
    {
        $request = Request::create('/');
        $request->attributes->set('_route', 'some_route_name');

        $attributes = (new ServerRequestAttributeProvider())->getAttributes($request);

        $this->assertEquals('some_route_name', $attributes['http.route']);
    }

    public function testItSetsUserAgentOriginal(): void
    {
        $attributes = (new ServerRequestAttributeProvider())->getAttributes(Request::create('/', server: ['HTTP_USER_AGENT' => 'Prometheus/1.1']));

        $this->assertEquals('Prometheus/1.1', $attributes['user_agent.original']);
    }

    public function testItSetsHeaders(): void
    {
        $request = Request::create('https://www.some-host.com/', 'POST', content: '{}');
        $request->headers->set('content-length', '2');

        $attributes = (new ServerRequestAttributeProvider())->getAttributes($request);

        $this->assertEquals(['www.some-host.com'], $attributes['http.request.header.host']);
        $this->assertEquals(['2'], $attributes['http.request.header.content_length']);
    }

    public function testItSetsCapturedHeaders(): void
    {
        $attributes = (new ServerRequestAttributeProvider(null, ['x-foo']))->getAttributes(Request::create('/', server: ['HTTP_X_FOO' => 'bar']));

        $this->assertEquals(['bar'], $attributes['http.request.header.x_foo']);
    }

    public function testItRedactsSensitiveQueryValues(): void
    {
        $provider = new ServerRequestAttributeProvider(null, [], new UrlSanitizer(['code']));

        $attributes = $provider->getAttributes(Request::create('https://www.example.com/callback?code=abc&state=1'));

        $this->assertEquals('code=REDACTED&state=1', $attributes['url.query']);
    }
}
