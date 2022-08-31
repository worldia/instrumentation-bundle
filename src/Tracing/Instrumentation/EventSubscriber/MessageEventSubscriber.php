<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Instrumentation\EventSubscriber;

use Instrumentation\Semantics\Attribute\MessageAttributeProviderInterface;
use Instrumentation\Tracing\Instrumentation\MainSpanContextInterface;
use Instrumentation\Tracing\Instrumentation\Messenger\AttributesStamp;
use Instrumentation\Tracing\Instrumentation\Messenger\OperationNameStamp;
use Instrumentation\Tracing\Instrumentation\TracerAwareTrait;
use Instrumentation\Tracing\Propagation\Messenger\PropagationStrategyStamp;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SDK\Trace\Span;
use OpenTelemetry\SDK\Trace\SpanProcessorInterface;
use OpenTelemetry\SemConv\TraceAttributeValues;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\AbstractWorkerMessageEvent;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Stamp\BusNameStamp;

class MessageEventSubscriber implements EventSubscriberInterface
{
    use TracerAwareTrait;

    private bool $createSubSpan = true;

    /**
     * @var \SplObjectStorage<SpanInterface, ScopeInterface>
     */
    private \SplObjectStorage $scopes;

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageReceivedEvent::class => [['onConsume', 100]],
            WorkerMessageHandledEvent::class => [['onHandled', -100]],
            WorkerMessageFailedEvent::class => [['onHandled', -100]],
        ];
    }

    public function __construct(protected TracerProviderInterface $tracerProvider, protected SpanProcessorInterface $spanProcessor, protected MessageAttributeProviderInterface $attributeProvider, protected MainSpanContextInterface $mainSpanContext)
    {
        $this->scopes = new \SplObjectStorage();
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

            $strategy = $this->getStrategy($event->getEnvelope());

            $builder = $this->getTracer()
                ->spanBuilder($operation)
                ->setSpanKind(SpanKind::KIND_CONSUMER)
                ->setAttributes($attributes);

            if (PropagationStrategyStamp::STRATEGY_LINK === $strategy) {
                $linkContext = Span::getCurrent()->getContext();
                $builder->setNoParent()->addLink($linkContext);
            }

            $span = $builder->startSpan();

            $this->scopes[$span] = $span->activate();
        } else {
            $span = Span::getCurrent();

            foreach ($attributes as $key => $value) {
                $span->setAttribute($key, $value);
            }
        }

        $this->mainSpanContext->setMainSpan($span);
    }

    public function onHandled(AbstractWorkerMessageEvent $event): void
    {
        $span = $this->mainSpanContext->getMainSpan();

        if ($event instanceof WorkerMessageFailedEvent) {
            $span->recordException($event->getThrowable());
            $span->setStatus(StatusCode::STATUS_ERROR);
        }

        if ($this->createSubSpan) {
            if (null !== $scope = $this->scopes[$span]) {
                $scope->detach();
                unset($this->scopes[$span]); // Free memory
            }
            $span->end();
            $this->mainSpanContext->setCurrent();
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
        $name = \get_class($envelope->getMessage());
        /** @var OperationNameStamp|null $stamp */
        $stamp = $envelope->last(OperationNameStamp::class);
        if ($stamp) {
            $name = $stamp->getOperationName();
        }

        return sprintf('message.%s %s', $name, $operation);
    }

    /**
     * @return array<non-empty-string,non-empty-string>|array{
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

        /** @var AttributesStamp|null $stamp */
        $stamp = $envelope->last(AttributesStamp::class);
        if ($stamp) {
            $attributes = array_merge($attributes, $stamp->getAttributes());
        }

        return $attributes;
    }

    private function getStrategy(Envelope $envelope): string
    {
        /** @var PropagationStrategyStamp|null $stamp */
        $stamp = $envelope->last(PropagationStrategyStamp::class);
        if ($stamp) {
            return $stamp->getStrategy();
        }

        return PropagationStrategyStamp::STRATEGY_LINK;
    }
}
