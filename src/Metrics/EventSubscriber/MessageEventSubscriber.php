<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Metrics\EventSubscriber;

use Instrumentation\Tracing\Instrumentation\Messenger\AbstractDateTimeStamp;
use Instrumentation\Tracing\Instrumentation\Messenger\ConsumedAtStamp;
use Instrumentation\Tracing\Instrumentation\Messenger\HandledAtStamp;
use Instrumentation\Tracing\Instrumentation\Messenger\SentAtStamp;
use OpenTelemetry\API\Metrics\GaugeInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\AbstractWorkerMessageEvent;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Stamp\BusNameStamp;

class MessageEventSubscriber implements EventSubscriberInterface
{
    private MeterInterface $meter;
    private GaugeInterface|null $handlingGauge = null;

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageReceivedEvent::class => [['onConsume', 100]],
            WorkerMessageHandledEvent::class => [['onHandled', -512]],
            WorkerMessageFailedEvent::class => [['onFailed', -512]],
        ];
    }

    public function __construct(private readonly MeterProviderInterface $meterProvider)
    {
        $this->meter = $this->meterProvider->getMeter('instrumentation');
    }

    public function onConsume(WorkerMessageReceivedEvent $event): void
    {
        $attributes = $this->getAttributes($event->getEnvelope());

        $this->handlingGauge = $this->meter->createGauge('messages_handling', null, 'Number of messages this instance is currently handling');
        $this->handlingGauge->record(1, $attributes);

        if (!$time = $this->getTimeInSecondsBetweenStamps($event->getEnvelope(), SentAtStamp::class, ConsumedAtStamp::class)) {
            return;
        }

        $this->meter->createHistogram('messages_time_to_consume_seconds', 's', 'Time between a message is sent and consumed')->record($time, $attributes);
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

        $attributes = $this->getAttributes($event->getEnvelope());
        $this->meter->createCounter('messages_'.$counter.'_total')->add(1, $attributes);

        $this->handlingGauge?->record(-1, $attributes);

        if (!$time = $this->getTimeInSecondsBetweenStamps($event->getEnvelope(), ConsumedAtStamp::class, HandledAtStamp::class)) {
            return;
        }

        $this->meter->createHistogram('messages_time_to_handle_seconds', 's', 'Time between a message is consumed and handled')->record($time, $attributes);
    }

    /**
     * @return array{bus:string,class:string}
     */
    private function getAttributes(Envelope $envelope): array
    {
        $busName = 'default';

        /** @var BusNameStamp|null $stamp */
        $stamp = $envelope->last(BusNameStamp::class);
        if ($stamp) {
            $busName = $stamp->getBusName();
        }

        return ['bus' => $busName, 'class' => \get_class($envelope->getMessage())];
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
