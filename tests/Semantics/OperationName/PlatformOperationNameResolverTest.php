<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Semantics\OperationName;

use Instrumentation\Semantics\OperationName\PlatformOperationNameResolver;
use Instrumentation\Semantics\OperationName\PlatformOperationNameResolverInterface;
use PHPUnit\Framework\TestCase;

class PlatformOperationNameResolverTest extends TestCase
{
    public function testItImplementsPlatformOperationNameResolverInterface(): void
    {
        $this->assertTrue(is_a(PlatformOperationNameResolver::class, PlatformOperationNameResolverInterface::class, true));
    }

    public function testItDefaultsWhenNoOperationNameIsGiven(): void
    {
        $resolver = new PlatformOperationNameResolver();

        $this->assertSame('symfony_ai', $resolver->getOperationName('gpt-4o', []));
    }

    public function testItUsesTheOperationNameFromOptions(): void
    {
        $resolver = new PlatformOperationNameResolver();

        $this->assertSame('chat', $resolver->getOperationName('gpt-4o', ['extra' => ['operation_name' => 'chat']]));
    }

    public function testItFallsBackWhenOperationNameIsNotANonEmptyString(): void
    {
        $resolver = new PlatformOperationNameResolver();

        $this->assertSame('symfony_ai', $resolver->getOperationName('gpt-4o', ['extra' => ['operation_name' => 123]]));
        $this->assertSame('symfony_ai', $resolver->getOperationName('gpt-4o', ['extra' => ['operation_name' => '']]));
    }
}
