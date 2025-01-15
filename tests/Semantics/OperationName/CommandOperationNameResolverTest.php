<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Semantics\OperationName;

use Instrumentation\Semantics\OperationName\CommandOperationNameResolver;
use Instrumentation\Semantics\OperationName\CommandOperationNameResolverInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;

class CommandOperationNameResolverTest extends TestCase
{
    public function testItImplementsCommandAttributeProviderInterface(): void
    {
        $this->assertTrue(is_a(CommandOperationNameResolver::class, CommandOperationNameResolverInterface::class, true));
    }

    public function testItResolvesOperationNameForNamedCommand(): void
    {
        $command = new Command('some:command:name');
        $resolver = new CommandOperationNameResolver();
        $operation = $resolver->getOperationName($command);

        $this->assertEquals('cli some:command:name', $operation);
    }

    public function testItResolvesOperationNameForUnnamedCommand(): void
    {
        $command = new Command();
        $resolver = new CommandOperationNameResolver();
        $operation = $resolver->getOperationName($command);

        $this->assertEquals('cli unknown-command', $operation);
    }
}
