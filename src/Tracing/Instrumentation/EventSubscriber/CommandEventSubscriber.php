<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Instrumentation\EventSubscriber;

use Instrumentation\Tracing\Instrumentation\MainSpanContextInterface;
use Instrumentation\Tracing\Instrumentation\TracerAwareTrait;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\ScopeInterface;
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
            ConsoleCommandEvent::class => [['onCommand', 100]],
            ConsoleErrorEvent::class => [['onError', -100]],
            ConsoleTerminateEvent::class => [['onTerminate', -100]],
            ConsoleSignalEvent::class => [['onSignal', -100]],
        ];
    }

    public function __construct(protected TracerProviderInterface $tracerProvider, protected MainSpanContextInterface $mainSpanContext)
    {
    }

    public function onCommand(ConsoleCommandEvent $event): void
    {
        $name = $event->getCommand()?->getName() ?: 'unknown-command';

        $this->span = $this->startSpan(sprintf('cli %s', $name), ['command' => $name]);
        $this->scope = $this->span->activate();

        $this->mainSpanContext->setMainSpan($this->span);
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
    }
}
