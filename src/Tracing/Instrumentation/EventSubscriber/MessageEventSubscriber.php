<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Instrumentation\EventSubscriber;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SDK\Trace\Span;
use OpenTelemetry\SDK\Trace\TracerProvider;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpReceivedStamp;
use Symfony\Component\Messenger\Bridge\Redis\Transport\RedisReceivedStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\AbstractWorkerMessageEvent;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

class MessageEventSubscriber implements EventSubscriberInterface
{
    use TracerSubscriberTrait;

    private bool $createSubSpan = true;

    public static function getSubscribedEvents(): array
    {
        return [
            SendMessageToTransportsEvent::class => [['onSend', 100]],
            WorkerMessageReceivedEvent::class => [['onConsume', 100]],
            WorkerMessageHandledEvent::class => [['onHandled', -100]],
            WorkerMessageFailedEvent::class => [['onHandled', -100]],
        ];
    }

    public function onSend(SendMessageToTransportsEvent $event): void
    {
        $span = Span::getCurrent();
        $span->addEvent(\get_class($event));
    }

    public function onConsume(WorkerMessageReceivedEvent $event): void
    {
        $attributes = array_merge($this->getAttributes($event->getEnvelope()), [
            'messaging.operation' => 'process',
            'messaging.consumer_id' => gethostname(),
            'messaging.destination' => $event->getReceiverName(),
        ]);

        if ($this->createSubSpan) {
            $span = $this->getTracer()
                ->spanBuilder($this->getOperationName($event->getEnvelope(), 'process'))
                ->setSpanKind(SpanKind::KIND_CONSUMER)
                ->setAttributes($attributes)
                ->startSpan();
            $span->activate();
        } else {
            $span = Span::getCurrent();

            foreach ($attributes as $key => $value) {
                $span->setAttribute($key, $value);
            }
        }

        $span->addEvent(\get_class($event));
    }

    public function onHandled(AbstractWorkerMessageEvent $event): void
    {
        $span = Span::getCurrent();

        if ($event instanceof WorkerMessageFailedEvent) {
            $span->recordException($event->getThrowable());
        }

        $span->addEvent(\get_class($event));

        if ($this->createSubSpan) {
            $span->end();
        }

        /** @var TracerProvider $provider */
        $provider = $this->tracerProvider;
        $provider->forceFlush();
    }

    /**
     * @see https://github.com/open-telemetry/opentelemetry-specification/blob/v1.9.0/specification/trace/semantic_conventions/messaging.md#operation-names
     *
     * @param string $operation One of 'send', 'receive' or 'process'
     *
     * @return non-empty-string
     */
    private function getOperationName(Envelope $envelope, string $operation): string
    {
        return sprintf('%s %s', \get_class($envelope->getMessage()), $operation);
    }

    /**
     * @return array{
     *           'messenger.message':class-string,
     *           'messenger.bus'?:string,
     *           'messaging.destination_kind':'queue'|'topic',
     *           'messaging.system'?:'rabbitmq'|'redis',
     *           'messaging.protocol'?:'AMQP',
     *           'messaging.message_id'?:string
     *         }
     */
    private function getAttributes(Envelope $envelope): array
    {
        $attributes = [
            'messaging.destination_kind' => 'queue',
            'messenger.message' => \get_class($envelope->getMessage()),
        ];

        if ($envelope->last(RedisReceivedStamp::class)) { // @phpstan-ignore-line
            $attributes['messaging.system'] = 'redis';
        } elseif ($envelope->last(AmqpReceivedStamp::class)) { // @phpstan-ignore-line
            $attributes['messaging.system'] = 'rabbitmq';
            $attributes['messaging.protocol'] = 'AMQP';
        }

        /** @var TransportMessageIdStamp|null $stamp */
        $stamp = $envelope->last(TransportMessageIdStamp::class);
        if ($stamp) {
            $attributes['messaging.message_id'] = (string) $stamp->getId();
        }

        /** @var BusNameStamp|null $stamp */
        $stamp = $envelope->last(BusNameStamp::class);
        if ($stamp) {
            $attributes['messenger.bus'] = $stamp->getBusName();
        }

        return $attributes;
    }
}
