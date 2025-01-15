<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Baggage\Propagation\EventSubscriber;

use Instrumentation\Baggage\Propagation\EventSubscriber\MessengerEventSubscriber;
use Instrumentation\Baggage\Propagation\Messenger\BaggageStamp;
use OpenTelemetry\API\Baggage\Baggage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

class MessengerEventSubscriberTest extends TestCase
{
    public function testItImplementsEventSubscriberInterface()
    {
        $this->assertTrue(is_a(MessengerEventSubscriber::class, EventSubscriberInterface::class, true));
    }

    public function testItSubscribesToRelevantEvents(): void
    {
        $events = MessengerEventSubscriber::getSubscribedEvents();
        $this->assertArrayHasKey(SendMessageToTransportsEvent::class, $events);
        $this->assertArrayHasKey(WorkerMessageReceivedEvent::class, $events);
    }

    public function testItAddsBaggageStamp(): void
    {
        $envelope = new Envelope(new \stdClass());
        $event = new SendMessageToTransportsEvent($envelope, []);

        $subscriber = new MessengerEventSubscriber();

        $subscriber->onSend($event);

        $stamp = $event->getEnvelope()->last(BaggageStamp::class);

        $this->assertNotNull($stamp);
    }

    public function testItInitializesContextFromStamp(): void
    {
        $scope1 = Baggage::getBuilder()->set('foo', 'bar')->build()->activate();
        $stamp = new BaggageStamp();
        $scope1->detach();

        $this->assertNull(Baggage::getCurrent()->getValue('foo'));

        $envelope = new Envelope(new \stdClass(), [$stamp]);
        $event = new WorkerMessageReceivedEvent($envelope, 'receiver');

        $subscriber = new MessengerEventSubscriber();

        $subscriber->onConsume($event);

        $this->assertEquals('bar', Baggage::getCurrent()->getValue('foo'));

        $subscriber->onHandled($event);
        $this->assertNull(Baggage::getCurrent()->getValue('foo'));
    }
}
