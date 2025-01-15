<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Baggage\Propagation\Messenger;

use Instrumentation\Baggage\Propagation\Messenger\BaggageStamp;
use OpenTelemetry\API\Baggage\Baggage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Stamp\StampInterface;

class BaggageStampTest extends TestCase
{
    public function testItImplementsStampInterface()
    {
        $this->assertTrue(is_a(BaggageStamp::class, StampInterface::class, true));
    }

    public function testItGetsBaggageFromContext(): void
    {
        $scope = Baggage::getBuilder()->set('foo', 'bar')->build()->activate();

        $baggageStamp = new BaggageStamp();

        $this->assertEquals('foo=bar', $baggageStamp->getBaggage());
        $scope->detach();
    }
}
