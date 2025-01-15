<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Semantics\Attribute;

use Instrumentation\Semantics\Attribute\ClientRequestAttributeProvider;
use Instrumentation\Semantics\Attribute\ClientRequestAttributeProviderInterface;
use PHPUnit\Framework\TestCase;

class ClientRequestAttributeProviderTest extends TestCase
{
    public function testItImplementsClientRequestAttributeProviderInterface(): void
    {
        $this->assertTrue(is_a(ClientRequestAttributeProvider::class, ClientRequestAttributeProviderInterface::class, true));
    }

    public function testItGetsMinimalAttributes(): void
    {
        $provider = new ClientRequestAttributeProvider();

        $attributes = $provider->getAttributes('GET', 'https://www.example.com/some/url');

        $this->assertArrayHasKey('http.request.method', $attributes);
        $this->assertArrayHasKey('url.full', $attributes);
        $this->assertArrayHasKey('url.path', $attributes);
        $this->assertArrayHasKey('url.scheme', $attributes);

        $this->assertEquals('GET', $attributes['http.request.method']);
        $this->assertEquals('https://www.example.com/some/url', $attributes['url.full']);
        $this->assertEquals('/some/url', $attributes['url.path']);
        $this->assertEquals('https', $attributes['url.scheme']);
    }

    public function testItCapturesHeaders(): void
    {
        $provider = new ClientRequestAttributeProvider(['x-foo', 'x-other-captured']);

        $attributes = $provider->getAttributes('GET', '/some/url', ['x-foo' => 'bar', 'x-not-captured' => 'value']);

        $this->assertArrayHasKey('http.request.header.x_foo', $attributes);
        $this->assertEquals('bar', $attributes['http.request.header.x_foo']);

        $this->assertArrayNotHasKey('http.request.header.x_other_captured', $attributes);
        $this->assertArrayNotHasKey('http.request.header.x_not_captured', $attributes);
    }

    public function testItCapturesHeadersGivenAsArrays(): void
    {
        $provider = new ClientRequestAttributeProvider(['x-foo']);

        $attributes = $provider->getAttributes('GET', '/some/url', ['x-foo' => ['bar', 'baz']]);

        $this->assertArrayHasKey('http.request.header.x_foo', $attributes);
        $this->assertEquals('bar,baz', $attributes['http.request.header.x_foo']);
    }

    public function testItDoesntThrowOnInvalidUrl(): void
    {
        $provider = new ClientRequestAttributeProvider();

        $attributes = $provider->getAttributes('GET', '');

        $this->assertIsArray($attributes);
    }
}
