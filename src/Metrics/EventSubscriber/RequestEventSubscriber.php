<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Metrics\EventSubscriber;

use Instrumentation\Metrics\MetricProviderInterface;
use Instrumentation\Metrics\RegistryInterface;
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
            'response_codes_100_total' => [
                'type' => Counter::TYPE,
                'help' => 'Number of requests with a status code in the 1XX range',
            ],
            'response_codes_200_total' => [
                'type' => Counter::TYPE,
                'help' => 'Number of requests with a status code in the 2XX range',
            ],
            'response_codes_300_total' => [
                'type' => Counter::TYPE,
                'help' => 'Number of requests with a status code in the 3XX range',
            ],
            'response_codes_400_total' => [
                'type' => Counter::TYPE,
                'help' => 'Number of requests with a status code in the 4XX range',
            ],
            'response_codes_500_total' => [
                'type' => Counter::TYPE,
                'help' => 'Number of requests with a status code in the 5XX range',
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
    public function __construct(private RegistryInterface $registry, private array $blacklist)
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
        $code = ((int) substr((string) $event->getResponse()->getStatusCode(), 0, 1)) * 100;

        $this->registry->getGauge('requests_handling')->dec();
        $this->registry->getHistogram('response_times_seconds')->observe($time);
        $this->registry->getCounter(sprintf('response_codes_%s_total', $code))->inc();
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
