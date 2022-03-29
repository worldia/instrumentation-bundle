<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace spec\Instrumentation\Baggage\Propagation\Messenger;

use Instrumentation\Baggage\Propagation\Messenger\BaggageStamp;
use OpenTelemetry\API\Baggage\Baggage;
use PhpSpec\ObjectBehavior;
use Symfony\Component\Messenger\Stamp\StampInterface;

class BaggageStampSpec extends ObjectBehavior
{
    public function let()
    {
        Baggage::getBuilder()->set('foo', 'bar')->build()->activate();
    }

    public function it_is_initializable(): void
    {
        $this->beAnInstanceOf(BaggageStamp::class);
    }

    public function it_implements_stamp_interface(): void
    {
        $this->shouldImplement(StampInterface::class);
    }

    public function it_gets_baggage_from_context(): void
    {
        $this->getBaggage()->shouldReturn('foo=bar');
    }
}
