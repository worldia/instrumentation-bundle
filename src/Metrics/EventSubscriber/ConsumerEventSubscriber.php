<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Metrics\EventSubscriber;

use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Metrics\UpDownCounterInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;

class ConsumerEventSubscriber implements EventSubscriberInterface
{
    private MeterInterface $meter;
    private UpDownCounterInterface|null $counter = null;

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerStartedEvent::class => [['onStart', 9999]],
            WorkerStoppedEvent::class => [['onStop', -9999]],
        ];
    }

    public function __construct(private readonly MeterProviderInterface $meterProvider)
    {
        $this->meter = $this->meterProvider->getMeter('instrumentation');
    }

    public function onStart(WorkerStartedEvent $event): void
    {
        $this->counter = $this->meter->createUpDownCounter('consumers_active', null, 'Number of active consumers');

        $this->counter->add(1, ['queue' => $this->getQueueAttribute($event)]);
    }

    public function onStop(WorkerStoppedEvent $event): void
    {
        $this->counter?->add(-1, ['queue' => $this->getQueueAttribute($event)]);
    }

    /**
     * @param WorkerStoppedEvent|WorkerStartedEvent $event
     */
    private function getQueueAttribute($event): string
    {
        if ($queues = $event->getWorker()->getMetadata()->getQueueNames()) {
            return implode(',', $queues);
        }

        return 'unknown';
    }
}
