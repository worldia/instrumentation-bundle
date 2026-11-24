<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Profiling\Request\EventListener;

use Instrumentation\Profiling\Encoding\PprofEncoder;
use Instrumentation\Profiling\Label\ProfileLabelProviderInterface;
use Instrumentation\Profiling\Shipping\ProfileShipperInterface;
use Instrumentation\Tracing\Bridge\MainSpanContextInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Continuous profiling of requests, one profile per sampled request, with Excimer's sampling timer.
 *
 * The timer runs while the main request is handled (kernel.request to kernel.terminate), in a long-lived worker as well: it is
 * stopped on kernel.terminate before the span export is flushed (so the profile does not contain the export), or by kernel.reset when
 * the request never terminated. The profile is then handed to the {@see ProfileShipperInterface} once the flush is done.
 *
 * Every sample carries the main span's id as the `span_id` label, and the span carries it as `pyroscope.profile.id` (configurable):
 * that is what makes Grafana show "Profiles for this span" on the trace and open that request's flame graph.
 *
 * Which requests are sampled: none when not `$active` or without the excimer extension; a share (`$requestRate`, 0..1) of them; and
 * always the one carrying the on-demand token (`$onDemandToken` in the `$onDemandHeader` header), whatever the rate is: with a rate of 0
 * that is the only profiled traffic, at no cost to the rest.
 */
final class RequestProfilingSubscriber implements EventSubscriberInterface, ResetInterface
{
    private readonly bool $enabled;
    private readonly LoggerInterface $logger;
    private readonly string $onDemandToken;
    private readonly string $onDemandHeader;
    private readonly string $spanAttribute;

    private \ExcimerProfiler|null $profiler = null;
    private float $startedAt = 0.0;
    private string|null $spanId = null;

    /** @var array{string, float, string|null, array<string, string>}|null the encoded profile waiting for the end of the flush */
    private array|null $pending = null;

    /**
     * @param iterable<ProfileLabelProviderInterface> $labelProviders
     */
    public function __construct(
        private readonly ProfileShipperInterface $shipper,
        private readonly PprofEncoder $encoder,
        private readonly iterable $labelProviders = [],
        private readonly MainSpanContextInterface|null $mainSpanContext = null,
        LoggerInterface|null $logger = null,
        bool $active = true,
        private readonly int $frequency = 50,
        private readonly float $requestRate = 1.0,
        private readonly string $clock = 'wall',
        string|null $onDemandToken = '',
        string|null $onDemandHeader = 'X-Profile-Token',
        string|null $spanAttribute = 'pyroscope.profile.id',
        private readonly int $maxDepth = 256,
    ) {
        $this->logger = $logger ?? new NullLogger();
        // An unset environment variable with a `default::` fallback arrives as null.
        $this->onDemandToken = $onDemandToken ?? '';
        $this->onDemandHeader = $onDemandHeader ?: 'X-Profile-Token';
        $this->spanAttribute = $spanAttribute ?? '';
        $this->enabled = $active && $frequency > 0 && \extension_loaded('excimer');
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onRequest', 1024]], // before the request span is opened: the profile covers all of the request
            KernelEvents::RESPONSE => [['onResponse', -4096]], // after the main span is named, so its id is final
            KernelEvents::TERMINATE => [['onTerminate', 1024], ['onTerminated', -1024]], // stop before the spans are flushed, ship after
        ];
    }

    public function onRequest(Event\RequestEvent $event): void
    {
        if (!$this->enabled || !$event->isMainRequest()) {
            return;
        }
        $this->shipper->reap();
        $this->abandon();

        $supplied = $event->getRequest()->headers->get($this->onDemandHeader);
        $onDemand = '' !== $this->onDemandToken && \is_string($supplied) && hash_equals($this->onDemandToken, $supplied);
        if (!$onDemand && $this->requestRate < 1.0 && mt_rand() / mt_getrandmax() >= $this->requestRate) {
            return;
        }

        $profiler = new \ExcimerProfiler();
        $profiler->setPeriod(1 / $this->frequency);
        $profiler->setEventType('cpu' === $this->clock ? \EXCIMER_CPU : \EXCIMER_REAL);
        $profiler->setMaxDepth($this->maxDepth);
        $this->startedAt = microtime(true);
        $profiler->start();
        $this->profiler = $profiler;
    }

    public function onResponse(Event\ResponseEvent $event): void
    {
        if (null === $this->profiler || !$event->isMainRequest()) {
            return;
        }
        $span = $this->mainSpanContext?->getMainSpan() ?? \OpenTelemetry\API\Trace\Span::getCurrent();
        $context = $span->getContext();
        if (!$context->isValid()) {
            return; // tracing is off: no id to link the profile to
        }
        $this->spanId = $context->getSpanId();
        if ('' !== $this->spanAttribute) {
            $span->setAttribute($this->spanAttribute, $this->spanId);
        }
    }

    public function onTerminate(Event\TerminateEvent $event): void
    {
        if (null === $this->profiler) {
            return;
        }
        $profiler = $this->profiler;
        $startedAt = $this->startedAt;
        $spanId = $this->spanId;
        $this->profiler = $this->spanId = null;

        try {
            $profiler->stop();
            $pprof = $this->encoder->encode(
                $profiler->getLog(),
                $this->clock,
                1 / $this->frequency,
                (string) $spanId,
                (int) ($startedAt * 1e9),
                (int) ((microtime(true) - $startedAt) * 1e9),
            );
            $this->pending = [$pprof, $startedAt, $spanId, $this->labels($event->getRequest())];
        } catch (\Throwable $e) {
            $this->logger->warning('Profile dropped: '.$e->getMessage());
        }
    }

    public function onTerminated(Event\TerminateEvent $event): void
    {
        if (null === $this->pending) {
            return;
        }
        [$pprof, $startedAt, , $labels] = $this->pending;
        $this->pending = null;

        try {
            $this->shipper->ship($pprof, $startedAt, $labels);
        } catch (\Throwable $e) {
            $this->logger->warning('Profile dropped: '.$e->getMessage());
        }
    }

    /** kernel.reset, between two requests of a worker: a profile still running belongs to a request that never terminated. */
    public function reset(): void
    {
        $this->abandon();
        $this->shipper->reap();
    }

    /** @return array<string, string> */
    private function labels(Request $request): array
    {
        $labels = [];
        foreach ($this->labelProviders as $provider) {
            $labels = array_merge($labels, $provider->labels($request)); // in order; a later provider wins
        }

        return array_filter($labels, static fn (string $v): bool => '' !== $v);
    }

    private function abandon(): void
    {
        $this->profiler?->stop();
        $this->profiler = $this->pending = $this->spanId = null;
    }
}
