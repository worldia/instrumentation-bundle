<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Metrics\EventSubscriber;

use Instrumentation\Metrics\MetricProviderInterface;
use Instrumentation\Metrics\RegistryInterface;
use Prometheus\Counter;
use Prometheus\Gauge;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Stamp\BusNameStamp;

class MessageEventSubscriber implements EventSubscriberInterface, MetricProviderInterface
{
    public static function getProvidedMetrics(): array
    {
        return [
            'messages_handling' => [
                'type' => Gauge::TYPE,
                'help' => 'Number of messages this instance is currently handling',
                'labels' => ['bus', 'class'],
            ],
            'messages_handled_total' => [
                'type' => Counter::TYPE,
                'help' => 'Number of messages handled successfully',
                'labels' => ['bus', 'class'],
            ],
            'messages_failed_total' => [
                'type' => Counter::TYPE,
                'help' => 'Number of messages handled with failure',
                'labels' => ['bus', 'class'],
            ],
        ];
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageReceivedEvent::class => [['onConsume', 100]],
            WorkerMessageHandledEvent::class => [['onHandled', -100]],
            WorkerMessageFailedEvent::class => [['onHandled', -100]],
        ];
    }

    public function __construct(private RegistryInterface $registry)
    {
    }

    public function onConsume(WorkerMessageReceivedEvent $event): void
    {
        $labels = $this->getLabels($event->getEnvelope());
        $this->registry->getGauge('messages_handling')->inc($labels);
    }

    public function onHandled(WorkerMessageHandledEvent $event): void
    {
        $labels = $this->getLabels($event->getEnvelope());
        $this->registry->getCounter('messages_handled_total')->inc($labels);
        $this->registry->getGauge('messages_handling')->dec($labels);
    }

    public function onFailed(WorkerMessageFailedEvent $event): void
    {
        $labels = $this->getLabels($event->getEnvelope());
        $this->registry->getCounter('messages_failed_total')->inc($labels);
        $this->registry->getGauge('messages_handling')->dec($labels);
    }

    /**
     * @return array{bus:string,class:string}
     */
    private function getLabels(Envelope $envelope): array
    {
        $busName = 'default';

        /** @var BusNameStamp|null $stamp */
        $stamp = $envelope->last(BusNameStamp::class);
        if ($stamp) {
            $busName = $stamp->getBusName();
        }

        return [
            'bus' => $busName,
            'class' => \get_class($envelope->getMessage()),
        ];
    }
}
