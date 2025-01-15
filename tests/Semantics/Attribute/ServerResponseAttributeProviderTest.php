<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Semantics\Attribute;

use Instrumentation\Semantics\Attribute\ServerResponseAttributeProvider;
use Instrumentation\Semantics\Attribute\ServerResponseAttributeProviderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class ServerResponseAttributeProviderTest extends TestCase
{
    public function testItImplementsServerResponseAttributeProviderInterface(): void
    {
        $this->assertTrue(is_a(ServerResponseAttributeProvider::class, ServerResponseAttributeProviderInterface::class, true));
    }

    public function testItSetsClientIp(): void
    {
        $response = new Response('', 200);

        $provider = new ServerResponseAttributeProvider();
        $attributes = $provider->getAttributes($response);

        $this->assertEquals(200, $attributes['http.response.status_code']);
    }
}
