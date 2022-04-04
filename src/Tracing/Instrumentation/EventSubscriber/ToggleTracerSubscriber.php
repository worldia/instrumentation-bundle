<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Instrumentation\EventSubscriber;

use Instrumentation\Tracing\Sampler\TogglableSampler;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

class ToggleTracerSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            RequestEvent::class => [['onRequest', 8092]],
            ConsoleCommandEvent::class => [['onCommand', 8092]],
            WorkerMessageReceivedEvent::class => [['onMessage', 8092]],
        ];
    }

    /**
     * @param array<string> $requestBlacklist
     * @param array<string> $commandBlacklist
     * @param array<string> $messageBlacklist
     */
    public function __construct(private TogglableSampler $sampler, private array $requestBlacklist, private array $commandBlacklist, private array $messageBlacklist)
    {
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $operation = $event->getRequest()->getPathInfo();

        if ($this->isBlacklisted($operation, $this->requestBlacklist)) {
            $this->sampler->dropNext();
        }

        if (null !== $force = $event->getRequest()->headers->get('x-trace')) {
            if ((bool) $force) {
                $this->sampler->recordAndSampleNext();
            } else {
                $this->sampler->dropNext();
            }
        }
    }

    public function onCommand(ConsoleCommandEvent $event): void
    {
        $operation = $event->getCommand()?->getDefaultName() ?: 'unknown-command';

        if ($this->isBlacklisted($operation, $this->commandBlacklist)) {
            $this->sampler->dropNext();
        }
    }

    public function onMessage(WorkerMessageReceivedEvent $event): void
    {
        $operation = \get_class($event->getEnvelope()->getMessage());

        if ($this->isBlacklisted($operation, $this->messageBlacklist)) {
            $this->sampler->dropNext();
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
