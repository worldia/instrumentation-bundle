<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Metrics\EventSubscriber;

use Instrumentation\Metrics\MetricProviderInterface;
use Instrumentation\Metrics\RegistryInterface;
use Prometheus\Gauge;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;

class ConsumerEventSubscriber implements EventSubscriberInterface, MetricProviderInterface
{
    public static function getProvidedMetrics(): array
    {
        return [
            'consumers_active' => [
                'type' => Gauge::TYPE,
                'help' => 'Number of active consumers',
                'labels' => ['queue'],
            ],
        ];
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerStartedEvent::class => [['onStart', 9999]],
            WorkerStoppedEvent::class => [['onStop', -9999]],
        ];
    }

    public function __construct(private RegistryInterface $registry)
    {
    }

    public function onStart(WorkerStartedEvent $event): void
    {
        $labels = [$this->getQueueLabel($event)];
        $this->registry->getGauge('consumers_active')->inc($labels);
    }

    public function onStop(WorkerStoppedEvent $event): void
    {
        $labels = [$this->getQueueLabel($event)];
        $this->registry->getGauge('consumers_active')->dec($labels);
    }

    /**
     * @param WorkerStoppedEvent|WorkerStartedEvent $event
     */
    private function getQueueLabel($event): string
    {
        if ($queues = $event->getWorker()->getMetadata()->getQueueNames()) {
            return implode(',', $queues);
        }

        return 'unknown';
    }
}
