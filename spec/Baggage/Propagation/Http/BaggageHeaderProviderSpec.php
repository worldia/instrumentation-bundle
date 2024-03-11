<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace spec\Instrumentation\Baggage\Propagation\Http;

use Instrumentation\Baggage\Propagation\Http\BaggageHeaderProvider;
use OpenTelemetry\API\Baggage\Baggage;
use OpenTelemetry\Context\ScopeInterface;
use PhpSpec\ObjectBehavior;

class BaggageHeaderProviderSpec extends ObjectBehavior
{
    private ScopeInterface|null $scope = null;

    public function let()
    {
        $this->scope = Baggage::getBuilder()->set('foo', 'bar')->build()->activate();
    }

    public function letGo(): void
    {
        $this->scope->detach();
    }

    public function it_is_initializable(): void
    {
        $this->beAnInstanceOf(BaggageHeaderProvider::class);
    }

    public function it_gets_baggage_header(): void
    {
        $this->getHeaderName()->shouldReturn('baggage');
        $this->getHeaderValue()->shouldReturn('foo=bar');
    }
}
