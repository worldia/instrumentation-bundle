<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Propagation\EventSubscriber;

use Instrumentation\Tracing\Propagation\ContextInitializer;
use Instrumentation\Tracing\Propagation\Messenger\TraceContextStamp;
use OpenTelemetry\Context\ScopeInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

class MessengerEventSubscriber implements EventSubscriberInterface
{
    private LoggerInterface $logger;
    private ?ScopeInterface $scope = null;

    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

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
        try {
            $event->setEnvelope($event->getEnvelope()->with(new TraceContextStamp()));
        } catch (\Throwable $error) {
            $this->logger->warning($error->getMessage(), ['exception' => $error]);
        }
    }

    public function onConsume(WorkerMessageReceivedEvent $event): void
    {
        $this->scope = ContextInitializer::fromMessage($event->getEnvelope());
    }

    public function onHandled(): void
    {
        $this->scope?->detach();
    }
}
