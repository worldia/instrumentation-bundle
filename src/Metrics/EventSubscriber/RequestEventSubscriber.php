<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Metrics\EventSubscriber;

use Instrumentation\Tracing\Bridge\MainSpanContextInterface;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\API\Common\Time\ClockInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use Opentelemetry\Proto\Trace\V1\Span\SpanKind;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event;
use Symfony\Component\HttpKernel\KernelEvents;

class RequestEventSubscriber implements EventSubscriberInterface
{
    private int|null $lastFlush = null;

    /**
     * @param array<string> $blacklist
     * @param int           $flushInterval Minimum seconds between two exports, 0 to export after every request
     */
    public function __construct(
        private readonly MeterProviderInterface $meterProvider,
        private array $blacklist,
        private MainSpanContextInterface|null $mainSpanContext = null,
        private readonly int $flushInterval = 10,
        private readonly ClockInterface|null $clock = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => [['onTerminate', 8092]],
        ];
    }

    public function onTerminate(Event\TerminateEvent $event): void
    {
        if ($event->isMainRequest() && !$this->isBlacklisted($event->getRequest())) {
            $operation = $this->mainSpanContext?->getOperationName() ?: 'unknown';

            $meter = $this->meterProvider->getMeter('instrumentation');
            $meter->createGauge('memory_usage_bytes', null, 'Memory usage of the request')->record(memory_get_peak_usage(), ['span_name' => $operation, 'span_kind' => SpanKind::name(SpanKind::SPAN_KIND_SERVER)]);
        }

        $this->flushIfDue();
    }

    /**
     * Long-running workers (FrankenPHP, RoadRunner) serve many requests per process, and the
     * metrics reader only exports on flush or shutdown. Exporting after every request would be one
     * OTLP call per request, so it is throttled to once per flush interval.
     */
    private function flushIfDue(): void
    {
        if (!method_exists($this->meterProvider, 'forceFlush')) {
            return;
        }

        $now = ($this->clock ?? Clock::getDefault())->now();

        if (null !== $this->lastFlush && $now - $this->lastFlush < $this->flushInterval * ClockInterface::NANOS_PER_SECOND) {
            return;
        }

        $this->lastFlush = $now;
        $this->meterProvider->forceFlush();
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
