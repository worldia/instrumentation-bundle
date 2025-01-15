<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Semantics\OperationName;

use Instrumentation\Semantics\OperationName\MessageOperationNameResolver;
use Instrumentation\Semantics\OperationName\MessageOperationNameResolverInterface;
use Instrumentation\Tracing\Messenger\Stamp\OperationNameStamp;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;

class MessageOperationNameResolverTest extends TestCase
{
    public function testItImplementsMessageAttributeProviderInterface(): void
    {
        $this->assertTrue(is_a(MessageOperationNameResolver::class, MessageOperationNameResolverInterface::class, true));
    }

    public function testItResolvesOperationNamFromStamp(): void
    {
        $envelope = new Envelope(new \stdClass());

        $resolver = new MessageOperationNameResolver();
        $operation = $resolver->getOperationName($envelope, 'process');

        $this->assertEquals('message stdClass process', $operation);
    }

    public function testItResolvesOperationNameFromStamp(): void
    {
        $envelope = new Envelope(new \stdClass(), [new OperationNameStamp('some-name')]);

        $resolver = new MessageOperationNameResolver();
        $operation = $resolver->getOperationName($envelope, 'process');

        $this->assertEquals('message some-name process', $operation);
    }
}
