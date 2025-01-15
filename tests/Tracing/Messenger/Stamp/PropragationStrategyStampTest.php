<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Tracing\Messenger\Stamp;

use Instrumentation\Tracing\Messenger\Stamp\PropagationStrategyStamp;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Stamp\StampInterface;

class PropragationStrategyStampTest extends TestCase
{
    public function testItImplementsStampInterface(): void
    {
        $stamp = new PropagationStrategyStamp(PropagationStrategyStamp::STRATEGY_LINK);
        $this->assertInstanceOf(StampInterface::class, $stamp);
    }

    public function testItThrowsWhenStrategyIsInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PropagationStrategyStamp('some-name');
    }

    public function testItReturnsStrategy(): void
    {
        $stamp = new PropagationStrategyStamp(PropagationStrategyStamp::STRATEGY_LINK);

        $this->assertEquals('link', $stamp->getStrategy());
    }
}
