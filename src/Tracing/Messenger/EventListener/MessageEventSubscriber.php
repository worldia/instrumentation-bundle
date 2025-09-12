<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Messenger\EventListener;

use Instrumentation\Semantics\Attribute\MessageAttributeProviderInterface;
use Instrumentation\Semantics\OperationName\MessageOperationNameResolverInterface;
use Instrumentation\Tracing\Bridge\ContextInitializer;
use Instrumentation\Tracing\Bridge\MainSpanContextInterface;
use Instrumentation\Tracing\Messenger\Stamp\AbstractDateTimeStamp;
use Instrumentation\Tracing\Messenger\Stamp\AttributesStamp;
use Instrumentation\Tracing\Messenger\Stamp\ConsumedAtStamp;
use Instrumentation\Tracing\Messenger\Stamp\HandledAtStamp;
use Instrumentation\Tracing\Messenger\Stamp\PropagationStrategyStamp;
use Instrumentation\Tracing\Messenger\Stamp\SentAtStamp;
use Instrumentation\Tracing\Messenger\Stamp\TraceContextStamp;
use Instrumentation\Tracing\TracerAwareTrait;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SDK\Trace\Span;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\AbstractWorkerMessageEvent;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Stamp\BusNameStamp;

class MessageEventSubscriber implements EventSubscriberInterface
{
    use TracerAwareTrait;

    private bool $createSubSpan = true;
    private ScopeInterface|null $consumptionScope = null;

    /**
     * @var \SplObjectStorage<SpanInterface, ScopeInterface>
     */
    private \SplObjectStorage $scopes;

    public static function getSubscribedEvents(): array
    {
        return [
            SendMessageToTransportsEvent::class => [['onSend', 512]],
            WorkerMessageReceivedEvent::class => [['onConsume', 512]], // before all SF listeners
            WorkerMessageHandledEvent::class => [['onHandled', -512]],
            WorkerMessageFailedEvent::class => [['onHandled', -512]],
        ];
    }

    public function __construct(
        protected TracerProviderInterface $tracerProvider,
        private readonly MainSpanContextInterface $mainSpanContext,
        private readonly MessageOperationNameResolverInterface $operationNameResolver,
        private readonly MessageAttributeProviderInterface $attributeProvider,
        private readonly bool $flushAfterHandling = true,
    ) {
        $this->scopes = new \SplObjectStorage();
    }

    public function onSend(SendMessageToTransportsEvent $event): void
    {
        $event->setEnvelope($event->getEnvelope()
            ->with(new TraceContextStamp())
            ->with(new SentAtStamp())
        );
    }

    public function onConsume(WorkerMessageReceivedEvent $event): void
    {
        $this->consumptionScope = ContextInitializer::fromMessage($event->getEnvelope());

        $event->addStamps(new ConsumedAtStamp());

        $attributes = array_merge($this->getAttributes($event->getEnvelope()), [
            'messaging.operation' => 'process',
            'messaging.consumer_id' => gethostname(),
            'messaging.destination' => $event->getReceiverName(),
        ]);

        if ($this->createSubSpan) {
            $operationName = $this->operationNameResolver->getOperationName(
                $event->getEnvelope(),
                'process'
            );

            $strategy = $this->getStrategy($event->getEnvelope());

            $builder = $this->getTracer()
                ->spanBuilder($operationName)
                ->setSpanKind(SpanKind::KIND_CONSUMER)
                ->setAttributes($attributes);

            if (PropagationStrategyStamp::STRATEGY_LINK === $strategy) {
                $linkContext = Span::getCurrent()->getContext();
                $builder->setParent(false)->addLink($linkContext);
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

        $event->addStamps(new HandledAtStamp());

        foreach ([
            'sent_at' => SentAtStamp::class,
            'consumed_at' => ConsumedAtStamp::class,
            'handled_at' => HandledAtStamp::class,
        ] as $attribute => $stampFqdn) {
            /** @var AbstractDateTimeStamp $stamp */
            if (!$stamp = $event->getEnvelope()->last($stampFqdn)) {
                continue;
            }

            $span->setAttribute('messenger.'.$attribute, $stamp->getDate()->format('Y-m-d H:i:s.u'));
        }

        if ($this->createSubSpan) {
            if (null !== $scope = $this->scopes[$span]) {
                $scope->detach();
                unset($this->scopes[$span]); // Free memory
            }
            $span->end();
            $this->mainSpanContext->setCurrent();
        }

        $this->consumptionScope?->detach();

        if ($this->flushAfterHandling && method_exists($this->tracerProvider, 'forceFlush')) {
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
