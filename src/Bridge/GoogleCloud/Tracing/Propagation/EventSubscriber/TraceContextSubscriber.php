<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Bridge\GoogleCloud\Tracing\Propagation\EventSubscriber;

use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event;
use Symfony\Component\HttpKernel\KernelEvents;

class TraceContextSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onRequestEvent', 1001]],
        ];
    }

    public function onRequestEvent(Event\RequestEvent $event): void
    {
        $request = $event->getRequest();

        if ($request->headers->get(TraceContextPropagator::TRACEPARENT)) {
            return;
        }

        if (!$header = $request->headers->get('x-cloud-trace-context')) {
            return;
        }

        if (!$header = $this->toW3CHeader($header)) {
            return;
        }

        $request->headers->add([TraceContextPropagator::TRACEPARENT => $header]);
    }

    private function toW3CHeader(string $header): ?string
    {
        preg_match('/^(?<trace>[A-Za-z0-9]{32})\/(?<span>[A-Za-z0-9]*)?;?(?<options>.*)$/', $header, $matches);

        if (!isset($matches['trace']) || !isset($matches['span'])) {
            return null;
        }

        $traceId = $matches['trace'];
        $spanId = str_pad($matches['span'], 16, '0', \STR_PAD_LEFT);

        parse_str($matches['options'] ?? '', $options);
        $sampled = $options['o'] ?? true;

        return '00-'.$traceId.'-'.$spanId.'-'.($sampled ? '01' : '00');
    }
}
