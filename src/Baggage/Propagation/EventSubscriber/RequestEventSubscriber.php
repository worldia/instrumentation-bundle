<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Baggage\Propagation\EventSubscriber;

use Instrumentation\Baggage\Propagation\ContextInitializer;
use OpenTelemetry\Context\ScopeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event;
use Symfony\Component\HttpKernel\KernelEvents;

class RequestEventSubscriber implements EventSubscriberInterface
{
    private ScopeInterface|null $scope = null;

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onRequest', 256]],
            KernelEvents::TERMINATE => [['onTerminate', -256]],
        ];
    }

    public function onRequest(Event\RequestEvent $event): void
    {
        $this->scope = ContextInitializer::fromRequest($event->getRequest());
    }

    public function onTerminate(): void
    {
        $this->scope?->detach();
    }
}
