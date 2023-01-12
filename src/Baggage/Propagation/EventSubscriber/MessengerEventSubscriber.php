<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Baggage\Propagation\EventSubscriber;

use Instrumentation\Baggage\Propagation\ContextInitializer;
use Instrumentation\Baggage\Propagation\Messenger\BaggageStamp;
use OpenTelemetry\Context\ScopeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

class MessengerEventSubscriber implements EventSubscriberInterface
{
    private ?ScopeInterface $scope = null;

    public static function getSubscribedEvents(): array
    {
        return [
            SendMessageToTransportsEvent::class => [['onSend', 1000]],
            WorkerMessageReceivedEvent::class => [['onConsume', 1001]],
            WorkerMessageHandledEvent::class => [['onHandled', -512]],
            WorkerMessageFailedEvent::class => [['onHandled', -512]],
        ];
    }

    public function onSend(SendMessageToTransportsEvent $event): void
    {
        $event->setEnvelope($event->getEnvelope()->with(new BaggageStamp()));
    }

    public function onConsume(WorkerMessageReceivedEvent $event): void
    {
        $this->scope = ContextInitializer::fromMessage($event->getEnvelope());
    }

    /**
     * @param WorkerMessageHandledEvent|WorkerMessageFailedEvent $event
     */
    public function onHandled($event): void
    {
        $this->scope?->detach();
    }
}
