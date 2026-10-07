<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Tracing\Bridge;

use Instrumentation\Tracing\Bridge\MainSpanContext;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\SDK\Trace\Span;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Service\ResetInterface;

class MainSpanContextTest extends TestCase
{
    public function testItImplementsResetInterface(): void
    {
        $this->assertTrue(is_a(MainSpanContext::class, ResetInterface::class, true));
    }

    public function testResetClearsTheMainSpanAndOperationName(): void
    {
        $span = $this->createStub(SpanInterface::class);

        $context = new MainSpanContext();
        $context->setMainSpan($span);
        $context->setOperationName('GET /users');

        $this->assertSame($span, $context->getMainSpan());
        $this->assertSame('GET /users', $context->getOperationName());

        $context->reset();

        $this->assertSame(Span::getCurrent(), $context->getMainSpan());
        $this->assertNull($context->getOperationName());
    }
}
