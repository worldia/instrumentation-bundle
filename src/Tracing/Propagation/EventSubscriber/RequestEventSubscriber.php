<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Propagation\EventSubscriber;

use Instrumentation\Tracing\Propagation\ContextInitializer;
use Instrumentation\Tracing\Propagation\ForcableIdGenerator;
use Instrumentation\Tracing\Propagation\IncomingTraceHeaderResolverInterface;
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

    public function __construct(private ForcableIdGenerator $forcableIdGenerator, private ?IncomingTraceHeaderResolverInterface $incomingTraceResolver = null)
    {
    }

    public function onRequest(Event\RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if ($this->incomingTraceResolver) {
            $traceId = $this->incomingTraceResolver->getTraceId($event->getRequest());
            $spanId = $this->incomingTraceResolver->getSpanId($event->getRequest());
            $sampled = $this->incomingTraceResolver->isSampled($event->getRequest());

            if (null !== $traceId && null !== $spanId && null !== $sampled) {
                $w3cHeader = sprintf('00-%s-%s-%s', $traceId, $spanId, $sampled ? '01' : '00');
                ContextInitializer::fromW3CHeader($w3cHeader);

                return;
            }

            if ($traceId) {
                $this->forcableIdGenerator->setTraceId($traceId);
                if ($spanId) {
                    $this->forcableIdGenerator->setSpanId($spanId);
                }

                return;
            }
        }

        ContextInitializer::fromRequest($event->getRequest());
    }
}
