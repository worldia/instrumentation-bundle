<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace spec\Instrumentation\Baggage\Propagation\EventSubscriber;

use OpenTelemetry\API\Baggage\Baggage;
use PhpSpec\ObjectBehavior;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use spec\Instrumentation\IsolateContext;

class RequestEventSubscriberSpec extends ObjectBehavior
{
    use IsolateContext;

    public function let()
    {
        $this->forkMainContext();
        Baggage::getEmpty()->activate();
    }

    public function letGo(): void
    {
        $this->restoreMainContext();
    }

    public function it_is_initializable(): void
    {
        $this->beAnInstanceOf(RequestEventSubscriber::class);
    }

    public function it_subscribes_to_relevant_events(): void
    {
        $this->shouldImplement(EventSubscriberInterface::class);

        $this->getSubscribedEvents()->shouldHaveKey('kernel.request');
    }

    public function it_initializes_context(HttpKernelInterface $kernel): void
    {
        $request = new Request(server: ['HTTP_BAGGAGE' => 'foo=bar']);

        expect(Baggage::getCurrent()->getValue('foo'))->shouldReturn(null);

        $this->onRequest(new RequestEvent($kernel->getWrappedObject(), $request, HttpKernelInterface::MAIN_REQUEST));

        expect(Baggage::getCurrent()->getValue('foo'))->shouldReturn('bar');
    }
}
