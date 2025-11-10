<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Semantics\Attribute;

use Instrumentation\Semantics\Attribute\ServerRequestAttributeProvider;
use Instrumentation\Semantics\Attribute\ServerRequestAttributeProviderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

class ServerRequestAttributeProviderTest extends TestCase
{
    public function testItImplementsServerRequestAttributeProviderInterface(): void
    {
        $this->assertTrue(is_a(ServerRequestAttributeProvider::class, ServerRequestAttributeProviderInterface::class, true));
    }

    protected function expect(): array
    {
        $request = $this->createMock(Request::class);
        $parameters = $this->createMock(ParameterBag::class);
        $headers = $this->createMock(HeaderBag::class);

        $request->attributes = $parameters;
        $request->headers = $headers;

        return [
            Request::class => $request,
            ParameterBag::class => $parameters,
            HeaderBag::class => $headers,
        ];
    }

    public function testItSetsClientIp(): void
    {
        [Request::class => $request] = $this->expect();

        $request->expects($this->once())->method('getClientIp')->willReturn('10.11.12.13');

        $provider = new ServerRequestAttributeProvider();
        $attributes = $provider->getAttributes($request);

        $this->assertEquals('10.11.12.13', $attributes['client.address']);
    }

    public function testItSetsServerAddressFromHostHeader(): void
    {
        [Request::class => $request] = $this->expect();

        $provider = new ServerRequestAttributeProvider();
        $request->expects($this->atMost(2))->method('getHost')->willReturn('www.example.com');

        $attributes = $provider->getAttributes($request);

        $this->assertEquals('www.example.com', $attributes['server.address']);
    }

    public function testItSetsServerAddressFromServerNameConstructorArg(): void
    {
        [Request::class => $request] = $this->expect();

        $provider = new ServerRequestAttributeProvider('www.test.com');

        $attributes = $provider->getAttributes($request);

        $this->assertEquals('www.test.com', $attributes['server.address']);
    }

    public function testItSetsPort(): void
    {
        [Request::class => $request] = $this->expect();

        $provider = new ServerRequestAttributeProvider();
        $request->expects($this->once())->method('getPort')->willReturn(443);

        $attributes = $provider->getAttributes($request);

        $this->assertEquals('443', $attributes['server.port']);
    }

    public function testItSetsNetworkProtocolName(): void
    {
        [Request::class => $request] = $this->expect();

        $provider = new ServerRequestAttributeProvider();
        $request->expects($this->once())->method('getProtocolVersion')->willReturn('HTTP/1.1');

        $attributes = $provider->getAttributes($request);

        $this->assertEquals('1.1', $attributes['network.protocol.version']);
        $this->assertEquals('http', $attributes['network.protocol.name']);
    }

    public function testItSetsUrlScheme(): void
    {
        [Request::class => $request] = $this->expect();

        $provider = new ServerRequestAttributeProvider();
        $request->expects($this->once())->method('getScheme')->willReturn('https');

        $attributes = $provider->getAttributes($request);

        $this->assertEquals('https', $attributes['url.scheme']);
    }

    public function testItSetsUrlPath(): void
    {
        [Request::class => $request] = $this->expect();

        $provider = new ServerRequestAttributeProvider();
        $request->expects($this->once())->method('getPathInfo')->willReturn('/some/path');

        $attributes = $provider->getAttributes($request);

        $this->assertEquals('/some/path', $attributes['url.path']);
    }

    public function testItSetsUrlQuery(): void
    {
        [Request::class => $request] = $this->expect();

        $provider = new ServerRequestAttributeProvider();
        $request->expects($this->once())->method('getQueryString')->willReturn('foo=bar&bar=baz');

        $attributes = $provider->getAttributes($request);

        $this->assertEquals('foo=bar&bar=baz', $attributes['url.query']);
    }

    public function testItSetsHttpRequestMethod(): void
    {
        [Request::class => $request] = $this->expect();

        $provider = new ServerRequestAttributeProvider();
        $request->expects($this->once())->method('getMethod')->willReturn('PATCH');

        $attributes = $provider->getAttributes($request);

        $this->assertEquals('PATCH', $attributes['http.request.method']);
    }

    public function testItSetsHttpRoute(): void
    {
        [Request::class => $request, ParameterBag::class => $attributes] = $this->expect();

        $provider = new ServerRequestAttributeProvider();
        $attributes->expects($this->once())->method('get')->with('_route')->willReturn('some_route_name');

        $attributes = $provider->getAttributes($request);

        $this->assertEquals('some_route_name', $attributes['http.route']);
    }

    public function testItSetsUserAgentOriginal(): void
    {
        [Request::class => $request, HeaderBag::class => $headers] = $this->expect();

        $provider = new ServerRequestAttributeProvider();
        $headers->method('get')->willReturnCallback(function (string $param) {
            return match ($param) {
                'user-agent' => 'Prometheus/1.1',
                default => 'some-value',
            };
        });

        $attributes = $provider->getAttributes($request);

        $this->assertEquals('Prometheus/1.1', $attributes['user_agent.original']);
    }

    public function testItSetsHeaders(): void
    {
        [Request::class => $request, HeaderBag::class => $headers] = $this->expect();

        $provider = new ServerRequestAttributeProvider();
        $headers->method('has')->willReturn(true);
        $headers->method('get')->willReturnCallback(function (string $param) {
            return match ($param) {
                'host' => 'www.some-host.com',
                'content-length' => '2',
                default => 'some-value',
            };
        });

        $attributes = $provider->getAttributes($request);

        $this->assertEquals(['www.some-host.com'], $attributes['http.request.header.host']);
        $this->assertEquals(['2'], $attributes['http.request.header.content_length']);
    }

    public function testItSetsCapturedHeaders(): void
    {
        [Request::class => $request, HeaderBag::class => $headers] = $this->expect();

        $provider = new ServerRequestAttributeProvider(null, ['x-foo']);
        $headers->method('has')->willReturn(true);
        $headers->method('get')->willReturnCallback(function (string $param) {
            return match ($param) {
                'x-foo' => 'bar',
                default => 'some-value',
            };
        });

        $attributes = $provider->getAttributes($request);

        $this->assertEquals(['bar'], $attributes['http.request.header.x_foo']);
    }
}
