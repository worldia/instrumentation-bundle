<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace spec\Instrumentation\Tracing\Instrumentation\EventSubscriber;

use Instrumentation\Semantics\Attribute\MessageAttributeProviderInterface;
use Instrumentation\Semantics\OperationName\MessageOperationNameResolverInterface;
use Instrumentation\Tracing\Instrumentation\MainSpanContext;
use Instrumentation\Tracing\Instrumentation\Messenger\AttributesStamp;
use Instrumentation\Tracing\TracerInterface;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SDK\Trace\Span;
use OpenTelemetry\SDK\Trace\SpanProcessorInterface;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Stamp\BusNameStamp;

class MessageEventSubscriberSpec extends ObjectBehavior
{
    private MainSpanContext $mainSpanContext;

    public function let(
        TracerProviderInterface $tracerProvider,
        SpanProcessorInterface $spanProcessor,
        MessageOperationNameResolverInterface $operationNameResolver,
        MessageAttributeProviderInterface $attributeProvider,
        TracerInterface $tracer,
        SpanBuilderInterface $spanBuilder,
        SpanInterface $span,
        ScopeInterface $scope,
    ): void {
        $tracerProvider->getTracer('io.opentelemetry.contrib.php')->willReturn($tracer);
        $tracer->spanBuilder(Argument::type('string'))->willReturn($spanBuilder);
        $spanBuilder->setSpanKind(Argument::type('int'))->willReturn($spanBuilder);
        $spanBuilder->setAttributes(Argument::type('iterable'))->willReturn($spanBuilder);
        $spanBuilder->addLink(Argument::cetera())->willReturn($spanBuilder);
        $spanBuilder->setNoParent()->willReturn($spanBuilder);
        $spanBuilder->startSpan()->willReturn($span);
        $span->activate()->willReturn($scope);
        $span->recordException(Argument::cetera())->willReturn($span);
        $span->setStatus(Argument::cetera())->willReturn($span);
        $scope->detach()->willReturn(0);

        $spanProcessor->forceFlush()->willReturn(true);
        $operationNameResolver->getOperationName(Argument::type(Envelope::class), 'process')->willReturn('message.stdClass process');

        $attributeProvider->getAttributes(Argument::type(Envelope::class))->willReturn([
            'from_provider' => 'value',
        ]);

        $this->beConstructedWith(
            $tracerProvider,
            $this->mainSpanContext = new MainSpanContext(),
            $operationNameResolver,
            $attributeProvider,
            $spanProcessor,
        );
    }

    public function it_starts_a_new_span_when_receiving_a_message(
        TracerInterface $tracer,
        SpanBuilderInterface $spanBuilder,
        SpanInterface $span,
    ): void {
        $envelope = new Envelope(new \stdClass());

        $this->onConsume(new WorkerMessageReceivedEvent($envelope, 'receiver name'));

        $tracer->spanBuilder('message.stdClass process')->shouldHaveBeenCalled();
        $spanBuilder->setSpanKind(SpanKind::KIND_CONSUMER)->shouldHaveBeenCalled();
        $spanBuilder->setAttributes([
            'from_provider' => 'value',
            'messaging.operation' => 'process',
            'messaging.consumer_id' => gethostname(),
            'messaging.destination' => 'receiver name',
            'messenger.message' => 'stdClass',
        ])->shouldHaveBeenCalled();
        expect($this->mainSpanContext->getMainSpan())->shouldBe($span);
    }

    public function it_starts_a_new_span_when_receiving_a_message_with_bus_name_stamp(
        TracerInterface $tracer,
        SpanBuilderInterface $spanBuilder,
        SpanInterface $span,
    ): void {
        $envelope = new Envelope(new \stdClass(), [new BusNameStamp('bus_name')]);

        $this->onConsume(new WorkerMessageReceivedEvent($envelope, 'receiver name'));

        $tracer->spanBuilder('message.stdClass process')->shouldHaveBeenCalled();
        $spanBuilder->setSpanKind(SpanKind::KIND_CONSUMER)->shouldHaveBeenCalled();
        $spanBuilder->setAttributes([
            'from_provider' => 'value',
            'messaging.operation' => 'process',
            'messaging.consumer_id' => gethostname(),
            'messaging.destination' => 'receiver name',
            'messenger.message' => 'stdClass',
            'messenger.bus' => 'bus_name',
        ])->shouldHaveBeenCalled();
        expect($this->mainSpanContext->getMainSpan())->shouldBe($span);
    }

    public function it_starts_a_new_span_when_receiving_a_message_with_attributes_stamp(
        TracerInterface $tracer,
        SpanBuilderInterface $spanBuilder,
        SpanInterface $span,
    ): void {
        $envelope = new Envelope(new \stdClass(), [new AttributesStamp([
            'from_stamp' => 'value',
        ])]);

        $this->onConsume(new WorkerMessageReceivedEvent($envelope, 'receiver name'));

        $tracer->spanBuilder('message.stdClass process')->shouldHaveBeenCalled();
        $spanBuilder->setSpanKind(SpanKind::KIND_CONSUMER)->shouldHaveBeenCalled();
        $spanBuilder->setAttributes([
            'from_provider' => 'value',
            'messaging.operation' => 'process',
            'messaging.consumer_id' => gethostname(),
            'messaging.destination' => 'receiver name',
            'messenger.message' => 'stdClass',
            'from_stamp' => 'value',
        ])->shouldHaveBeenCalled();
        expect($this->mainSpanContext->getMainSpan())->shouldBe($span);
    }

    public function it_saves_and_clean_the_span_when_message_has_been_handled(
        ScopeInterface $scope,
        SpanInterface $span,
        SpanProcessorInterface $spanProcessor,
    ): void {
        $envelope = new Envelope(new \stdClass());
        $this->onConsume(new WorkerMessageReceivedEvent($envelope, 'receiver name'));

        $this->onHandled(new WorkerMessageHandledEvent($envelope, 'receiver name'));

        $scope->detach()->shouldHaveBeenCalled();
        $span->end()->shouldHaveBeenCalled();
        expect($this->mainSpanContext->getMainSpan())->shouldBe(Span::getCurrent());
        $spanProcessor->forceFlush()->shouldHaveBeenCalled();
    }

    public function it_saves_and_clean_the_span_when_message_has_failed(
        ScopeInterface $scope,
        SpanInterface $span,
        SpanProcessorInterface $spanProcessor,
    ): void {
        $envelope = new Envelope(new \stdClass());
        $this->onConsume(new WorkerMessageReceivedEvent($envelope, 'receiver name'));
        $failedEvent = new WorkerMessageFailedEvent($envelope, 'receiver name', new \Exception());

        $this->onHandled($failedEvent);

        $span->recordException($failedEvent->getThrowable())->shouldHaveBeenCalled();
        $span->setStatus(StatusCode::STATUS_ERROR)->shouldHaveBeenCalled();
        $scope->detach()->shouldHaveBeenCalled();
        $span->end()->shouldHaveBeenCalled();
        expect($this->mainSpanContext->getMainSpan())->shouldBe(Span::getCurrent());
        $spanProcessor->forceFlush()->shouldHaveBeenCalled();
    }
}
