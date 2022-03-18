<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Propagation\EventSubscriber;

use Instrumentation\Tracing\Propagation\ContextInitializer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event;
use Symfony\Component\HttpKernel\KernelEvents;

class RequestEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onRequest', 1001]],
        ];
    }

    public function onRequest(Event\RequestEvent $event): void
    {
        ContextInitializer::fromRequest($event->getRequest());
    }
}
