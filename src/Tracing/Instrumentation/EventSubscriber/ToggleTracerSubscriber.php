<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Instrumentation\EventSubscriber;

use Instrumentation\Tracing\Instrumentation\TogglableTracerProvider;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

class ToggleTracerSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            RequestEvent::class => [['onRequest', 1000]],
            ConsoleCommandEvent::class => [['onCommand', 1000]],
            WorkerMessageReceivedEvent::class => [['onMessage', 1000]],
        ];
    }

    /**
     * @param array<string> $requestBlacklist
     * @param array<string> $commandBlacklist
     * @param array<string> $messageBlacklist
     */
    public function __construct(private TogglableTracerProvider $tracerProvider, private array $requestBlacklist, private array $commandBlacklist, private array $messageBlacklist)
    {
    }

    public function onRequest(RequestEvent $event): void
    {
        $operation = $event->getRequest()->getPathInfo();

        if ($this->isBlacklisted($operation, $this->requestBlacklist)) {
            $this->tracerProvider->disable();
        }
    }

    public function onCommand(ConsoleCommandEvent $event): void
    {
        $operation = $event->getCommand()?->getDefaultName() ?: 'unknown-command';

        if ($this->isBlacklisted($operation, $this->commandBlacklist)) {
            $this->tracerProvider->disable();
        }
    }

    public function onMessage(WorkerMessageReceivedEvent $event): void
    {
        $operation = \get_class($event->getEnvelope()->getMessage());

        if ($this->isBlacklisted($operation, $this->messageBlacklist)) {
            $this->tracerProvider->disable();
        }
    }

    /**
     * @param array<string> $blacklist
     */
    private function isBlacklisted(string $name, array $blacklist): bool
    {
        foreach ($blacklist as $pattern) {
            if (1 !== preg_match("|$pattern|", $name)) {
                continue;
            }

            return true;
        }

        return false;
    }
}
