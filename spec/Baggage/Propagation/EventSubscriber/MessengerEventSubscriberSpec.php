<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace spec\Instrumentation\Baggage\Propagation\EventSubscriber;

use Instrumentation\Baggage\Propagation\Messenger\BaggageStamp;
use OpenTelemetry\API\Baggage\Baggage;
use PhpSpec\ObjectBehavior;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Stamp\StampInterface;

class MessengerEventSubscriberSpec extends ObjectBehavior
{
    public function it_is_initializable(): void
    {
        $this->beAnInstanceOf(MessengerEventSubscriber::class);
    }

    public function it_subscribes_to_relevant_events(): void
    {
        $this->shouldImplement(EventSubscriberInterface::class);

        $this->getSubscribedEvents()->shouldHaveKey(SendMessageToTransportsEvent::class);
        $this->getSubscribedEvents()->shouldHaveKey(WorkerMessageReceivedEvent::class);
    }

    public function it_adds_baggage_stamp(): void
    {
        $envelope = new Envelope(new \stdClass());
        $event = new SendMessageToTransportsEvent($envelope);

        $this->onSend($event);

        $stamp = $event->getEnvelope()->last(BaggageStamp::class);

        expect($stamp)->shouldImplement(StampInterface::class);
        expect($stamp)->shouldBeAnInstanceOf(BaggageStamp::class);
    }

    public function it_initializes_context(): void
    {
        Baggage::getBuilder()->set('foo', 'bar')->build()->activate();
        $stamp = new BaggageStamp();
        Baggage::getEmpty()->activate();

        expect($stamp->getBaggage())->shouldReturn('foo=bar');
        expect(Baggage::getCurrent()->getValue('foo'))->shouldReturn(null);

        $envelope = new Envelope(new \stdClass(), [$stamp]);
        $event = new WorkerMessageReceivedEvent($envelope, 'receiver');

        $this->onConsume($event);

        expect(Baggage::getCurrent()->getValue('foo'))->shouldReturn('bar');
    }
}
