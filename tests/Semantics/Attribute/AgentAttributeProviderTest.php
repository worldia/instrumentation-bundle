<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Semantics\Attribute;

use Instrumentation\Semantics\Attribute\AgentAttributeProvider;
use Instrumentation\Semantics\Attribute\AgentAttributeProviderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;

class AgentAttributeProviderTest extends TestCase
{
    public function testItImplementsAgentAttributeProviderInterface(): void
    {
        $this->assertTrue(is_a(AgentAttributeProvider::class, AgentAttributeProviderInterface::class, true));
    }

    public function testItSetsGenAiAgentAttributes(): void
    {
        $agent = $this->createMock(AgentInterface::class);
        $agent->method('getName')->willReturn('my_agent');

        $provider = new AgentAttributeProvider();
        $attributes = $provider->getAttributes($agent);

        $this->assertSame('invoke_agent', $attributes['gen_ai.operation.name']);
        $this->assertSame('my_agent', $attributes['gen_ai.agent.name']);
    }
}
