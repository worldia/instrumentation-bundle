<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace spec\Instrumentation\Tracing\Instrumentation\Messenger;

use PhpSpec\ObjectBehavior;
use Symfony\Component\Messenger\Stamp\StampInterface;

class AttributesStampSpec extends ObjectBehavior
{
    public function let(): void
    {
        $this->beConstructedWith(['foo' => 'bar']);
    }

    public function it_implements_stamp_interface(): void
    {
        $this->shouldBeAnInstanceOf(StampInterface::class);
    }

    public function it_returns_operation_name(): void
    {
        $this->getAttributes()->shouldReturn(['foo' => 'bar']);
    }
}
