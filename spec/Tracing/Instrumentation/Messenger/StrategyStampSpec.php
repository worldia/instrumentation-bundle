<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace spec\Instrumentation\Tracing\Instrumentation\Messenger;

use Instrumentation\Tracing\Instrumentation\Messenger\StrategyStamp;
use PhpSpec\ObjectBehavior;
use Symfony\Component\Messenger\Stamp\StampInterface;

class StrategyStampSpec extends ObjectBehavior
{
    public function it_implements_stamp_interface(): void
    {
        $this->beConstructedWith(StrategyStamp::STRATEGY_LINK);
        $this->shouldBeAnInstanceOf(StampInterface::class);
    }

    public function it_fails_for_an_invalid_strategy(): void
    {
        $this->beConstructedWith('unexisting');
        $this->shouldThrow(\InvalidArgumentException::class)->duringInstantiation();
    }
}
