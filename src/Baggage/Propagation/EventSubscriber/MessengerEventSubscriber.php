<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Baggage\Propagation\EventSubscriber;

use Instrumentation\Baggage\Propagation\ContextInitializer;
use Instrumentation\Baggage\Propagation\Messenger\BaggageStamp;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

class MessengerEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            SendMessageToTransportsEvent::class => [['onSend', 1000]],
            WorkerMessageReceivedEvent::class => [['onConsume', 1001]],
        ];
    }

    public function onSend(SendMessageToTransportsEvent $event): void
    {
        $event->setEnvelope($event->getEnvelope()->with(new BaggageStamp()));
    }

    public function onConsume(WorkerMessageReceivedEvent $event): void
    {
        ContextInitializer::fromMessage($event->getEnvelope());
    }
}
