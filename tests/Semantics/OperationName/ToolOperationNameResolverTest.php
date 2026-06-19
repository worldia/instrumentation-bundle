<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Semantics\OperationName;

use Instrumentation\Semantics\OperationName\ToolOperationNameResolver;
use Instrumentation\Semantics\OperationName\ToolOperationNameResolverInterface;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\ToolCall;

class ToolOperationNameResolverTest extends TestCase
{
    public function testItImplementsToolOperationNameResolverInterface(): void
    {
        $this->assertTrue(is_a(ToolOperationNameResolver::class, ToolOperationNameResolverInterface::class, true));
    }

    public function testItResolvesExecuteToolOperationName(): void
    {
        $resolver = new ToolOperationNameResolver();

        $this->assertSame('execute_tool get_weather', $resolver->getOperationName(new ToolCall('call_123', 'get_weather')));
    }
}
