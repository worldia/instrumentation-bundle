<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Semantics\Attribute;

use Instrumentation\Semantics\Attribute\PlatformAttributeProvider;
use Instrumentation\Semantics\Attribute\PlatformAttributeProviderInterface;
use PHPUnit\Framework\TestCase;

class PlatformAttributeProviderTest extends TestCase
{
    public function testItImplementsPlatformAttributeProviderInterface(): void
    {
        $this->assertTrue(is_a(PlatformAttributeProvider::class, PlatformAttributeProviderInterface::class, true));
    }

    public function testItSetsGenAiAttributes(): void
    {
        $provider = new PlatformAttributeProvider();
        $attributes = $provider->getAttributes('openai', 'gpt-4o', 'chat');

        $this->assertSame('chat', $attributes['gen_ai.operation.name']);
        $this->assertSame('openai', $attributes['gen_ai.system']);
        $this->assertSame('gpt-4o', $attributes['gen_ai.request.model']);
    }
}
