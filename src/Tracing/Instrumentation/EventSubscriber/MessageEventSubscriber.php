<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Instrumentation\EventSubscriber;

use Instrumentation\Semantics\Attribute\MessageAttributeProviderInterface;
use Instrumentation\Semantics\OperationName\MessageOperationNameResolverInterface;
use Instrumentation\Tracing\Instrumentation\MainSpanContextInterface;
use Instrumentation\Tracing\Instrumentation\Messenger\AttributesStamp;
use Instrumentation\Tracing\Instrumentation\TracerAwareTrait;
use Instrumentation\Tracing\Propagation\Messenger\PropagationStrategyStamp;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SDK\Trace\Span;
use OpenTelemetry\SDK\Trace\SpanProcessorInterface;
use OpenTelemetry\SDK\Trace\TracerProvider;
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
            WorkerMessageReceivedEvent::class => [['onConsume', 512]], // before all SF listeners
            WorkerMessageHandledEvent::class => [['onHandled', -512]],
            WorkerMessageFailedEvent::class => [['onHandled', -512]],
        ];
    }

    public function __construct(
        protected TracerProviderInterface $tracerProvider,
        protected MainSpanContextInterface $mainSpanContext,
        protected MessageOperationNameResolverInterface $operationNameResolver,
        protected MessageAttributeProviderInterface $attributeProvider,
        protected SpanProcessorInterface $spanProcessor,
    ) {
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
            $operationName = $this->operationNameResolver->getOperationName(
                $event->getEnvelope(),
                TraceAttributeValues::MESSAGING_OPERATION_PROCESS
            );

            $strategy = $this->getStrategy($event->getEnvelope());

            $builder = $this->getTracer()
                ->spanBuilder($operationName)
                ->setSpanKind(SpanKind::KIND_CONSUMER)
                ->setAttributes($attributes);

            if (PropagationStrategyStamp::STRATEGY_LINK === $strategy) {
                $linkContext = Span::getCurrent()->getContext();
                $builder->setNoParent()->addLink($linkContext);
            }

            $span = $builder->startSpan();

            $this->scopes[$span] = $span->activate();

            $this->mainSpanContext->setOperationName($operationName);
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
        if ($this->tracerProvider instanceof TracerProvider) {
            $this->tracerProvider->forceFlush();
        }
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
