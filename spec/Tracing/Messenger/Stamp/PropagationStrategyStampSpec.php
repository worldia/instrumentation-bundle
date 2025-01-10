<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace spec\Instrumentation\Tracing\Messenger\Stamp;

use Instrumentation\Tracing\Messenger\Stamp\PropagationStrategyStamp;
use PhpSpec\ObjectBehavior;
use Symfony\Component\Messenger\Stamp\StampInterface;

class PropagationStrategyStampSpec extends ObjectBehavior
{
    public function it_implements_stamp_interface(): void
    {
        $this->beConstructedWith(PropagationStrategyStamp::STRATEGY_LINK);
        $this->shouldBeAnInstanceOf(StampInterface::class);
    }

    public function it_fails_for_an_invalid_strategy(): void
    {
        $this->beConstructedWith('unexisting');
        $this->shouldThrow(\InvalidArgumentException::class)->duringInstantiation();
    }
}
