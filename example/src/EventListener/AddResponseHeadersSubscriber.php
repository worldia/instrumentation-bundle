<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace App\EventListener;

use OpenTelemetry\API\Trace\LocalRootSpan;
use OpenTelemetry\SDK\Trace\Span;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event;
use Symfony\Component\HttpKernel\KernelEvents;

final class AddResponseHeadersSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onResponseEvent'],
        ];
    }

    public function onResponseEvent(Event\ResponseEvent $event): void
    {
        /** @var Span $span */
        $span = LocalRootSpan::current();

        if (!$span->isRecording()) {
            return;
        }

        $event->getResponse()->headers->add([
            'X-Trace-Id' => $span->getContext()->getTraceId(),
            'X-Span-Id' => $span->getContext()->getSpanId(),
            'X-Span-Name' => $span->getName() ?? 'unknown',
        ]);
    }
}
