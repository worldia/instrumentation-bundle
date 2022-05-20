<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Instrumentation\EventSubscriber;

use Instrumentation\Semantics\Attribute\MessageAttributeProviderInterface;
use Instrumentation\Tracing\Instrumentation\MainSpanContext;
use Instrumentation\Tracing\Instrumentation\Messenger\OperationNameStamp;
use Instrumentation\Tracing\Instrumentation\TracerAwareTrait;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\SDK\Trace\Span;
use OpenTelemetry\SDK\Trace\SpanProcessorInterface;
use OpenTelemetry\SemConv\TraceAttributeValues;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Stamp\BusNameStamp;

class MessageEventSubscriber implements EventSubscriberInterface
{
    use TracerAwareTrait;

    private bool $createSubSpan = true;

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageReceivedEvent::class => [['onConsume', 100]],
            WorkerMessageHandledEvent::class => [['onHandled', -100]],
            WorkerMessageFailedEvent::class => [['onHandled', -100]],
        ];
    }

    public function __construct(protected TracerProviderInterface $tracerProvider, protected SpanProcessorInterface $spanProcessor, protected MessageAttributeProviderInterface $attributeProvider, protected MainSpanContext $mainSpanContext)
    {
    }

    public function onConsume(WorkerMessageReceivedEvent $event): void
    {
        $attributes = array_merge($this->getAttributes($event->getEnvelope()), [
            'messaging.operation' => 'process',
            'messaging.consumer_id' => gethostname(),
            'messaging.destination' => $event->getReceiverName(),
        ]);

        if ($this->createSubSpan) {
            /** @var string&non-empty-string $operation */
            $operation = $this->getOperationName(
                $event->getEnvelope(),
                TraceAttributeValues::MESSAGING_OPERATION_PROCESS
            );

            $span = $this->getTracer()
                ->spanBuilder($operation)
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

        $this->mainSpanContext->setMainSpan($span);
    }

    public function onHandled(): void
    {
        $span = $this->mainSpanContext->getMainSpan();

        if ($this->createSubSpan) {
            $span->end();
        }

        $this->spanProcessor->forceFlush();
    }

    /**
     * @see https://github.com/open-telemetry/opentelemetry-specification/blob/v1.9.0/specification/trace/semantic_conventions/messaging.md#operation-names
     *
     * @param TraceAttributeValues::MESSAGING_OPERATION_* $operation One of 'send', 'receive' or 'process'
     */
    private function getOperationName(Envelope $envelope, string $operation): string
    {
        /** @var OperationNameStamp|null $stamp */
        $stamp = $envelope->last(OperationNameStamp::class);
        if ($stamp) {
            return $stamp->getOperationName();
        }

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
        $attributes = $this->attributeProvider->getAttributes($envelope);

        $attributes['messenger.message'] = \get_class($envelope->getMessage());

        /** @var BusNameStamp|null $stamp */
        $stamp = $envelope->last(BusNameStamp::class);
        if ($stamp) {
            $attributes['messenger.bus'] = $stamp->getBusName();
        }

        return $attributes;
    }
}
