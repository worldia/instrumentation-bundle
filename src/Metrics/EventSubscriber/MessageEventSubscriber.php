<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Metrics\EventSubscriber;

use Instrumentation\Metrics\MetricProviderInterface;
use Instrumentation\Metrics\RegistryInterface;
use Instrumentation\Tracing\Instrumentation\Messenger\AbstractDateTimeStamp;
use Instrumentation\Tracing\Instrumentation\Messenger\ConsumedAtStamp;
use Instrumentation\Tracing\Instrumentation\Messenger\HandledAtStamp;
use Instrumentation\Tracing\Instrumentation\Messenger\SentAtStamp;
use Prometheus\Counter;
use Prometheus\Gauge;
use Prometheus\Histogram;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\AbstractWorkerMessageEvent;
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
            'messages_time_to_consume_seconds' => [
                'type' => Histogram::TYPE,
                'help' => 'Time between a message is sent and consumed',
                'labels' => ['bus', 'class'],
            ],
            'messages_time_to_handle_seconds' => [
                'type' => Histogram::TYPE,
                'help' => 'Time between a message is consumed and handled',
                'labels' => ['bus', 'class'],
            ],
        ];
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageReceivedEvent::class => [['onConsume', 100]],
            WorkerMessageHandledEvent::class => [['onHandled', -512]],
            WorkerMessageFailedEvent::class => [['onFailed', -512]],
        ];
    }

    public function __construct(private RegistryInterface $registry)
    {
    }

    public function onConsume(WorkerMessageReceivedEvent $event): void
    {
        $labels = $this->getLabels($event->getEnvelope());
        $this->registry->getGauge('messages_handling')->inc($labels);

        if (!$time = $this->getTimeInSecondsBetweenStamps($event->getEnvelope(), SentAtStamp::class, ConsumedAtStamp::class)) {
            return;
        }

        $histogram = $this->registry->getHistogram('messages_time_to_consume_seconds');
        $histogram->observe($time, $labels);
    }

    public function onHandled(WorkerMessageHandledEvent $event): void
    {
        $this->afterHandling($event, false);
    }

    public function onFailed(WorkerMessageFailedEvent $event): void
    {
        $this->afterHandling($event, true);
    }

    private function afterHandling(AbstractWorkerMessageEvent $event, bool $failed): void
    {
        $counter = $failed ? 'failed' : 'handled';

        $labels = $this->getLabels($event->getEnvelope());
        $this->registry->getCounter('messages_'.$counter.'_total')->inc($labels);
        $this->registry->getGauge('messages_handling')->dec($labels);

        if (!$time = $this->getTimeInSecondsBetweenStamps($event->getEnvelope(), ConsumedAtStamp::class, HandledAtStamp::class)) {
            return;
        }

        $histogram = $this->registry->getHistogram('messages_time_to_handle_seconds');
        $histogram->observe($time, $labels);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function getLabels(Envelope $envelope): array
    {
        $busName = 'default';

        /** @var BusNameStamp|null $stamp */
        $stamp = $envelope->last(BusNameStamp::class);
        if ($stamp) {
            $busName = $stamp->getBusName();
        }

        return [$busName, \get_class($envelope->getMessage())];
    }

    /**
     * @param class-string<\Symfony\Component\Messenger\Stamp\StampInterface> $startStampFqdn
     * @param class-string<\Symfony\Component\Messenger\Stamp\StampInterface> $endStampFqdn
     */
    private function getTimeInSecondsBetweenStamps(Envelope $envelope, string $startStampFqdn, string $endStampFqdn): float|null
    {
        /** @var AbstractDateTimeStamp|null $startStamp */
        $startStamp = $envelope->last($startStampFqdn);
        /** @var AbstractDateTimeStamp|null $endStamp */
        $endStamp = $envelope->last($endStampFqdn);

        if (!$startStamp || !$endStamp) {
            return null;
        }

        return $this->getTimeDifferenceInSeconds($startStamp->getDate(), $endStamp->getDate());
    }

    private function getTimeDifferenceInSeconds(\DateTimeInterface $start, \DateTimeInterface $end): float
    {
        return (float) ((float) $end->format('U.u') - (float) $start->format('U.u'));
    }
}
