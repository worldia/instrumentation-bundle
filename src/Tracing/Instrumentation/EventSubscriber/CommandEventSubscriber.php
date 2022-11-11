<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Instrumentation\EventSubscriber;

use Instrumentation\Semantics\OperationName\CommandOperationNameResolverInterface;
use Instrumentation\Tracing\Instrumentation\MainSpanContextInterface;
use Instrumentation\Tracing\Instrumentation\TracerAwareTrait;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SDK\Trace\TracerProvider;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleSignalEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CommandEventSubscriber implements EventSubscriberInterface
{
    use TracerAwareTrait;

    private ?SpanInterface $span = null;
    private ?ScopeInterface $scope = null;

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleCommandEvent::class => [['onCommand', 512]], // before all SF listeners
            ConsoleErrorEvent::class => [['onError', -512]],
            ConsoleTerminateEvent::class => [['onTerminate', -512]],
            ConsoleSignalEvent::class => [['onSignal', -512]],
        ];
    }

    public function __construct(
        protected TracerProviderInterface $tracerProvider,
        protected MainSpanContextInterface $mainSpanContext,
        protected CommandOperationNameResolverInterface $operationNameResolver
    ) {
    }

    public function onCommand(ConsoleCommandEvent $event): void
    {
        $operationName = $this->operationNameResolver->getOperationName($event->getCommand());

        $this->span = $this->startSpan($operationName);
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

    public function onTerminate(): void
    {
        $this->closeTrace();
    }

    private function closeTrace(): void
    {
        $this->scope?->detach();
        $this->span?->end();
        $this->span = null;

        if ($this->tracerProvider instanceof TracerProvider) {
            $this->tracerProvider->shutdown();
        }
    }
}
