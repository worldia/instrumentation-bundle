<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Semantics\OperationName;

use Instrumentation\Semantics\OperationName\AgentOperationNameResolver;
use Instrumentation\Semantics\OperationName\AgentOperationNameResolverInterface;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;

class AgentOperationNameResolverTest extends TestCase
{
    public function testItImplementsAgentOperationNameResolverInterface(): void
    {
        $this->assertTrue(is_a(AgentOperationNameResolver::class, AgentOperationNameResolverInterface::class, true));
    }

    public function testItResolvesInvokeAgentOperationName(): void
    {
        $agent = $this->createMock(AgentInterface::class);
        $agent->method('getName')->willReturn('my_agent');

        $resolver = new AgentOperationNameResolver();

        $this->assertSame('invoke_agent my_agent', $resolver->getOperationName($agent));
    }
}
