<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Instrumentation\EventSubscriber;

use OpenTelemetry\API\Trace\SpanInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleEvent;
use Symfony\Component\Console\Event\ConsoleSignalEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CommandEventSubscriber implements EventSubscriberInterface
{
    use TracerSubscriberTrait;

    private ?SpanInterface $span = null;

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleCommandEvent::class => [['onCommand', 100]],
            ConsoleTerminateEvent::class => [['onTerminate', -100]],
            ConsoleSignalEvent::class => [['onSignal', -100]],
            ConsoleErrorEvent::class => [['onError', -100]],
        ];
    }

    public function onCommand(ConsoleCommandEvent $event): void
    {
        $name = $event->getCommand()?->getDefaultName() ?: 'unknown-command';

        $this->span = $this->startSpan($name);
        $this->addEvent($event);
    }

    public function onError(ConsoleErrorEvent $event): void
    {
        $this->span?->recordException($event->getError());
    }

    public function onSignal(ConsoleSignalEvent $event): void
    {
        $this->addEvent($event);
        $this->span?->end();
    }

    public function onTerminate(ConsoleTerminateEvent $event): void
    {
        $this->addEvent($event);
        $this->span?->end();
    }

    protected function addEvent(ConsoleEvent $event): void
    {
        $this->span?->addEvent(\get_class($event));
    }
}
