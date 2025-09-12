<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Tracing\Command\EventListener;

use Instrumentation\Semantics\OperationName\CommandOperationNameResolverInterface;
use Instrumentation\Tracing\Bridge\MainSpanContextInterface;
use Instrumentation\Tracing\Command\EventListener\CommandEventSubscriber;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\ScopeInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleSignalEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CommandEventSubscriberTest extends TestCase
{
    protected function expect(string|null $spanName = null): array
    {
        $scope = $this->createMock(ScopeInterface::class);
        $scope->expects($this->atMost(1))->method('detach');

        $span = $this->createMock(SpanInterface::class);
        $span->expects($this->atMost(1))->method('activate')->willReturn($scope);

        $spanBuilder = $this->createMock(SpanBuilderInterface::class);
        $spanBuilder->expects($this->once())->method('startSpan')->willReturn($span);
        $spanBuilder->expects($this->any())->method($this->anything())->willReturn($spanBuilder);

        $tracer = $this->createMock(TracerInterface::class);
        $tracer->expects($this->once())->method('spanBuilder')->with($spanName ?: $this->anything())->willReturn($spanBuilder);

        $tracerProvider = $this->createMock(TracerProviderInterface::class);
        $tracerProvider->expects($this->once())->method('getTracer')->willReturn($tracer);

        $mainSpanContext = $this->createMock(MainSpanContextInterface::class);

        $commandOperationNameResolver = $this->createMock(CommandOperationNameResolverInterface::class);

        $subscriber = new CommandEventSubscriber($tracerProvider, $mainSpanContext, $commandOperationNameResolver);

        return [
            CommandEventSubscriber::class => $subscriber,
            ScopeInterface::class => $scope,
            SpanInterface::class => $span,
            SpanBuilderInterface::class => $spanBuilder,
            TracerInterface::class => $tracer,
            TracerProviderInterface::class => $tracerProvider,
            MainSpanContextInterface::class => $mainSpanContext,
            CommandOperationNameResolverInterface::class => $commandOperationNameResolver,
        ];
    }

    public function testItImplementsEventSubscriberInterface()
    {
        $this->assertTrue(is_a(CommandEventSubscriber::class, EventSubscriberInterface::class, true));
    }

    public function testItSubscribesToRelevantEvents(): void
    {
        $events = CommandEventSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(ConsoleCommandEvent::class, $events);
        $this->assertArrayHasKey(ConsoleErrorEvent::class, $events);
        $this->assertArrayHasKey(ConsoleSignalEvent::class, $events);
        $this->assertArrayHasKey(ConsoleTerminateEvent::class, $events);
    }

    public function testItStartsNewSpanWhenReceivingEventForCommand(): void
    {
        [
            CommandEventSubscriber::class => $subscriber,
            CommandOperationNameResolverInterface::class => $commandOperationNameResolver,
        ] = $this->expect('cli test:cmd');

        $command = new Command();

        $commandOperationNameResolver->expects($this->once())->method('getOperationName')->with($command)->willReturn('cli test:cmd');

        $subscriber->onCommand($this->createConsoleCommandEvent($command));
    }

    public function testItStartsNewSpanWhenReceivingEventWithoutCommand(): void
    {
        [
            CommandEventSubscriber::class => $subscriber,
            CommandOperationNameResolverInterface::class => $commandOperationNameResolver,
        ] = $this->expect('cli test:cmd');

        $commandOperationNameResolver->expects($this->once())->method('getOperationName')->with(null)->willReturn('cli test:cmd');

        $subscriber->onCommand($this->createConsoleCommandEvent(null));
    }

    public function testItRecordsExceptionOnErrorEvent(): void
    {
        [
            CommandEventSubscriber::class => $subscriber,
            SpanInterface::class => $span,
        ] = $this->expect();

        $errorEvent = $this->createConsoleErrorEvent();

        $span->expects($this->once())->method('recordException')->with($errorEvent->getError());
        $span->expects($this->once())->method('setStatus')->with(StatusCode::STATUS_ERROR);

        $subscriber->onCommand($this->createConsoleCommandEvent());
        $subscriber->onError($errorEvent);
    }

    public function testItClosesSpanAndDetachesScopeOnTerminateSignal(): void
    {
        [
            CommandEventSubscriber::class => $subscriber,
            ScopeInterface::class => $scope,
            SpanInterface::class => $span,
        ] = $this->expect();

        $scope->expects($this->once())->method('detach');
        $span->expects($this->once())->method('end');

        $subscriber->onCommand($this->createConsoleCommandEvent());
        $subscriber->onSignal();
    }

    public function testItClosesSpanAndDetachesScopeOnTerminateEvent(): void
    {
        [
            CommandEventSubscriber::class => $subscriber,
            ScopeInterface::class => $scope,
            SpanInterface::class => $span,
        ] = $this->expect();

        $scope->expects($this->once())->method('detach');
        $span->expects($this->once())->method('end');

        $subscriber->onCommand($this->createConsoleCommandEvent());
        $subscriber->onTerminate($this->createConsoleTerminateEvent());
    }

    private function createConsoleCommandEvent(Command|null $command = null): ConsoleCommandEvent
    {
        return new ConsoleCommandEvent($command, new ArrayInput([]), new NullOutput());
    }

    private function createConsoleErrorEvent(): ConsoleErrorEvent
    {
        return new ConsoleErrorEvent(new ArrayInput([]), new NullOutput(), new \Exception());
    }

    private function createConsoleTerminateEvent(): ConsoleTerminateEvent
    {
        return new ConsoleTerminateEvent(new Command(), new ArrayInput([]), new NullOutput(), 0);
    }
}
