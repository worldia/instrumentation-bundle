<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Metrics\EventSubscriber;

use Instrumentation\Metrics\MetricProviderInterface;
use Instrumentation\Metrics\RegistryInterface;
use Instrumentation\Tracing\Instrumentation\MainSpanContextInterface;
use Prometheus\Counter;
use Prometheus\Gauge;
use Prometheus\Histogram;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event;
use Symfony\Component\HttpKernel\KernelEvents;

class RequestEventSubscriber implements EventSubscriberInterface, MetricProviderInterface
{
    public static function getProvidedMetrics(): array
    {
        return [
            'requests_handled_total' => [
                'type' => Counter::TYPE,
                'help' => 'Total requests handled by this instance',
            ],
            'requests_handling' => [
                'type' => Gauge::TYPE,
                'help' => 'Number of requests this instance is currently handling',
            ],
            'response_codes_total' => [
                'type' => Counter::TYPE,
                'help' => 'Number of requests per status code',
                'labels' => ['code', 'operation'],
            ],
            'response_times_seconds' => [
                'type' => Histogram::TYPE,
                'help' => 'Distribution of response times in seconds',
                'buckets' => Histogram::getDefaultBuckets(),
            ],
        ];
    }

    /**
     * @param array<string> $blacklist
     */
    public function __construct(private RegistryInterface $registry, private array $blacklist, private ?MainSpanContextInterface $mainSpanContext = null)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onRequest', 9999]],
            KernelEvents::TERMINATE => [['onTerminate', 8092]],
        ];
    }

    public function onRequest(Event\RequestEvent $event): void
    {
        if (!$event->isMainRequest() || $this->isBlacklisted($event->getRequest())) {
            return;
        }

        $this->registry->getCounter('requests_handled_total')->inc();
        $this->registry->getGauge('requests_handling')->inc();
    }

    public function onTerminate(Event\TerminateEvent $event): void
    {
        if (!$event->isMainRequest() || $this->isBlacklisted($event->getRequest())) {
            return;
        }

        $time = microtime(true) - $event->getRequest()->server->get('REQUEST_TIME_FLOAT');
        $code = sprintf('%sxx', substr((string) $event->getResponse()->getStatusCode(), 0, 1));
        $operation = $this->mainSpanContext?->getOperationName() ?: 'unknown';

        $this->registry->getGauge('requests_handling')->dec();
        $this->registry->getHistogram('response_times_seconds')->observe($time);
        $this->registry->getCounter('response_codes_total')->inc([$code, $operation]);
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
