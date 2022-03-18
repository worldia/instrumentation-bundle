<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Propagation\EventSubscriber;

use Instrumentation\Tracing\Propagation\ContextInitializer;
use Instrumentation\Tracing\Propagation\Messenger\TraceContextStamp;
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
        $event->setEnvelope($event->getEnvelope()->with(new TraceContextStamp()));
    }

    public function onConsume(WorkerMessageReceivedEvent $event): void
    {
        ContextInitializer::fromMessage($event->getEnvelope());
    }
}
