<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Semantics\Attribute;

use Instrumentation\Semantics\Attribute\ToolAttributeProvider;
use Instrumentation\Semantics\Attribute\ToolAttributeProviderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\ToolCall;

class ToolAttributeProviderTest extends TestCase
{
    public function testItImplementsToolAttributeProviderInterface(): void
    {
        $this->assertTrue(is_a(ToolAttributeProvider::class, ToolAttributeProviderInterface::class, true));
    }

    public function testItSetsGenAiToolAttributes(): void
    {
        $provider = new ToolAttributeProvider();
        $attributes = $provider->getAttributes(new ToolCall('call_123', 'get_weather', ['city' => 'Paris']));

        $this->assertSame('execute_tool', $attributes['gen_ai.operation.name']);
        $this->assertSame('get_weather', $attributes['gen_ai.tool.name']);
        $this->assertSame('call_123', $attributes['gen_ai.tool.call.id']);
    }
}
