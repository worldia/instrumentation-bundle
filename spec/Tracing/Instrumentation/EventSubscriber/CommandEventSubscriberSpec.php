<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace spec\Instrumentation\Tracing\Instrumentation\EventSubscriber;

use Instrumentation\Semantics\OperationName\CommandOperationNameResolverInterface;
use Instrumentation\Tracing\Instrumentation\MainSpanContext;
use Instrumentation\Tracing\TracerInterface;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\ScopeInterface;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class CommandEventSubscriberSpec extends ObjectBehavior
{
    private MainSpanContext $mainSpanContext;

    public function let(
        TracerProviderInterface $tracerProvider,
        TracerInterface $tracer,
        CommandOperationNameResolverInterface $operationNameResolver,
        SpanBuilderInterface $spanBuilder,
        SpanInterface $span,
        ScopeInterface $scope,
    ): void {
        $tracerProvider->getTracer('io.opentelemetry.contrib.php')->willReturn($tracer);

        $tracer->spanBuilder(Argument::type('string'))->willReturn($spanBuilder);
        $spanBuilder->setAttributes(Argument::type('iterable'))->willReturn($spanBuilder);
        $spanBuilder->startSpan()->willReturn($span);
        $span->activate()->willReturn($scope);
        $span->recordException(Argument::type(\Throwable::class))->willReturn($span);
        $span->setStatus(Argument::cetera())->willReturn($span);
        $scope->detach()->willReturn(0);

        $operationNameResolver->getOperationName(Argument::type(Command::class))->willReturn('cli test:cmd');
        $operationNameResolver->getOperationName(null)->willReturn('cli unknown-command');

        $this->beConstructedWith(
            $tracerProvider,
            $this->mainSpanContext = new MainSpanContext(),
            $operationNameResolver
        );
    }

    public function it_starts_new_span_when_receiving_event_for_a_named_command(
        TracerInterface $tracer,
        SpanBuilderInterface $spanBuilder,
        SpanInterface $span,
    ): void {
        $this->onCommand($this->createConsoleCommandEvent(new Command('test:cmd')));

        $tracer->spanBuilder('cli test:cmd')->shouldHaveBeenCalled();

        $span->activate()->shouldHaveBeenCalled();
        expect($this->mainSpanContext->getMainSpan())->shouldBe($span);
    }

    public function it_starts_new_span_when_receiving_event_for_an_unnamed_command(
        TracerInterface $tracer,
        SpanBuilderInterface $spanBuilder,
        SpanInterface $span,
    ): void {
        $this->onCommand($this->createConsoleCommandEvent(new Command()));

        $tracer->spanBuilder('cli test:cmd')->shouldHaveBeenCalled();
        $span->activate()->shouldHaveBeenCalled();
        expect($this->mainSpanContext->getMainSpan())->shouldBe($span);
    }

    public function it_starts_new_span_when_receiving_event_without_a_command(
        TracerInterface $tracer,
        SpanBuilderInterface $spanBuilder,
        SpanInterface $span,
    ): void {
        $this->onCommand($this->createConsoleCommandEvent());

        $tracer->spanBuilder('cli unknown-command')->shouldHaveBeenCalled();
        $span->activate()->shouldHaveBeenCalled();
        expect($this->mainSpanContext->getMainSpan())->shouldBe($span);
    }

    public function it_updates_span_when_receiving_error_event(SpanInterface $span): void
    {
        $this->onCommand($this->createConsoleCommandEvent());
        $errorEvent = $this->createConsoleErrorEvent();

        $this->onError($errorEvent);

        $span->recordException($errorEvent->getError())->shouldHaveBeenCalled();
        $span->setStatus(StatusCode::STATUS_ERROR)->shouldHaveBeenCalled();
    }

    public function it_closes_span_when_receiving_a_terminating_signal(
        ScopeInterface $scope,
        SpanInterface $span,
    ): void {
        $this->onCommand($this->createConsoleCommandEvent());

        $this->onSignal();

        $scope->detach()->shouldHaveBeenCalled();
        $span->end()->shouldHaveBeenCalled();
    }

    public function it_closes_span_when_receiving_terminate_event(
        ScopeInterface $scope,
        SpanInterface $span,
    ): void {
        $this->onCommand($this->createConsoleCommandEvent());

        $this->onTerminate();

        $scope->detach()->shouldHaveBeenCalled();
        $span->end()->shouldHaveBeenCalled();
    }

    public function it_does_nothing_when_error_happens_before_console_event_was_sent(
        ScopeInterface $scope,
        SpanInterface $span,
    ): void {
        $this->shouldNotThrow()->duringOnError($this->createConsoleErrorEvent());
        $this->shouldNotThrow()->duringOnSignal();
        $this->shouldNotThrow()->duringOnTerminate();

        $span->recordException(Argument::type(\Throwable::class))->shouldNotHaveBeenCalled();
        $span->setStatus(StatusCode::STATUS_ERROR)->shouldNotHaveBeenCalled();
        $scope->detach()->shouldNotHaveBeenCalled();
        $span->end()->shouldNotHaveBeenCalled();
    }

    private function createConsoleCommandEvent(Command|null $command = null): ConsoleCommandEvent
    {
        return new ConsoleCommandEvent($command, new ArrayInput([]), new NullOutput());
    }

    private function createConsoleErrorEvent(Command|null $command = null): ConsoleErrorEvent
    {
        return new ConsoleErrorEvent(new ArrayInput([]), new NullOutput(), new \Exception());
    }
}
