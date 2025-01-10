<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Command\EventListener;

use Instrumentation\Semantics\OperationName\CommandOperationNameResolverInterface;
use Instrumentation\Tracing\Bridge\MainSpanContextInterface;
use Instrumentation\Tracing\TracerAwareTrait;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SemConv\TraceAttributes;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleSignalEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CommandEventSubscriber implements EventSubscriberInterface
{
    use TracerAwareTrait;

    private SpanInterface|null $span = null;
    private ScopeInterface|null $scope = null;

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleCommandEvent::class => [['onCommand', 512]], // before all SF listeners
            ConsoleErrorEvent::class => [['onError', -1024]],
            ConsoleTerminateEvent::class => [['onTerminate', -1024]],
            ConsoleSignalEvent::class => [['onSignal', -1024]],
        ];
    }

    public function __construct(
        protected TracerProviderInterface $tracerProvider,
        protected MainSpanContextInterface $mainSpanContext,
        protected CommandOperationNameResolverInterface $operationNameResolver,
    ) {
    }

    public function onCommand(ConsoleCommandEvent $event): void
    {
        // cache:clear is not traceable because it doesn't dispatch the console.terminate event.
        // @see https://github.com/symfony/symfony/issues/28701
        if ('cache:clear' === $event->getCommand()?->getDefaultName()) {
            return;
        }

        $operationName = $this->operationNameResolver->getOperationName($event->getCommand());
        $attributes = [
            TraceAttributes::PROCESS_COMMAND => $operationName,
        ];

        $this->span = $this->getTracer()
            ->spanBuilder($operationName)
            ->setSpanKind(SpanKind::KIND_PRODUCER)
            ->setAttributes($attributes)
            ->startSpan();

        $this->scope = $this->span->activate();

        $this->mainSpanContext->setMainSpan($this->span);
        $this->mainSpanContext->setOperationName($operationName);
    }

    public function onError(ConsoleErrorEvent $event): void
    {
        $this->span?->recordException($event->getError());
        $this->span?->setStatus(StatusCode::STATUS_ERROR);
    }

    public function onSignal(): void
    {
        $this->closeTrace();
    }

    public function onTerminate(ConsoleTerminateEvent $event): void
    {
        if (0 !== $event->getExitCode()) {
            $this->span?->setStatus(StatusCode::STATUS_ERROR);
            $this->span?->setAttribute(TraceAttributes::PROCESS_EXIT_CODE, $event->getExitCode());
        }

        $this->closeTrace();
    }

    private function closeTrace(): void
    {
        $this->scope?->detach();
        $this->span?->end();
        $this->span = null;
    }
}
