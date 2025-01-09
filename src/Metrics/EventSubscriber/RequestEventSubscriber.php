<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Metrics\EventSubscriber;

use Instrumentation\Tracing\Instrumentation\MainSpanContextInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event;
use Symfony\Component\HttpKernel\KernelEvents;

class RequestEventSubscriber implements EventSubscriberInterface
{
    private MeterInterface $meter;

    /**
     * @param array<string> $blacklist
     */
    public function __construct(private readonly MeterProviderInterface $meterProvider, private array $blacklist, private MainSpanContextInterface|null $mainSpanContext = null)
    {
        $this->meter = $this->meterProvider->getMeter('instrumentation');
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => [['onTerminate', 8092]],
        ];
    }

    public function onTerminate(Event\TerminateEvent $event): void
    {
        if (!$event->isMainRequest() || $this->isBlacklisted($event->getRequest())) {
            return;
        }

        $time = microtime(true) - $event->getRequest()->server->get('REQUEST_TIME_FLOAT');
        $code = \sprintf('%sxx', substr((string) $event->getResponse()->getStatusCode(), 0, 1));
        $operation = $this->mainSpanContext?->getOperationName() ?: 'unknown';

        $this->meter->createCounter('requests_handled_total', null, 'Total requests handled by this instance')->add(1);
        $this->meter->createHistogram('response_times_seconds', 's', 'Distribution of response times in seconds')->record($time);
        $this->meter->createCounter('response_codes_total', null, 'Number of requests per status code')->add(1, ['code' => $code, 'operation' => $operation]);
    }

    private function isBlacklisted(Request $request): bool
    {
        $pathInfo = $request->getPathInfo();

        foreach ($this->blacklist as $pattern) {
            if (1 !== preg_match("|$pattern|", $pathInfo)) {
                continue;
            }

            return true;
        }

        return false;
    }
}
