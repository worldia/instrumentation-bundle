<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Semantics;

use Instrumentation\Semantics\ResourceInfoProvider;
use Instrumentation\Semantics\ResourceInfoProviderInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use PHPUnit\Framework\TestCase;

class ResourceInfoProviderTest extends TestCase
{
    public function testItImplementsEventSubscriberInterface()
    {
        $this->assertTrue(is_a(ResourceInfoProvider::class, ResourceInfoProviderInterface::class, true));
    }

    public function testItCreatesResourceInfo(): void
    {
        $provider = new ResourceInfoProvider();
        $info = $provider->getInfo();

        $this->assertInstanceOf(ResourceInfo::class, $info);
    }

    public function testItCreatesResourceInfoWithGivenAttributes(): void
    {
        $provider = new ResourceInfoProvider(['foo' => 'bar']);
        $info = $provider->getInfo();

        $this->assertInstanceOf(ResourceInfo::class, $info);
        $this->assertArrayHasKey('foo', $info->getAttributes()->toArray());
        $this->assertEquals('bar', $info->getAttributes()->get('foo'));
    }
}
