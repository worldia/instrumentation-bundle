<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Metrics;

use OpenTelemetry\API\Metrics\HistogramInterface;
use Prometheus\CollectorRegistry;
use Prometheus\Histogram;

class HistogramAdapter implements HistogramInterface
{
    public function __construct(
        private string $name,
        private string $description,
        private CollectorRegistry $collectorRegistry,
    ) {
    }

    /**
     * @param array{labels: array<string,mixed>, buckets?: array<int>} $attributes
     */
    public function record($amount, iterable $attributes = [], $context = null): void
    {
        if (!\is_array($attributes)) {
            return;
        }
        /** @var array<string> $labelNames */
        $labelNames = array_keys($attributes['labels'] ?? []);
        $labelValues = array_values($attributes['labels'] ?? []);

        $histogram = $this->collectorRegistry->getOrRegisterHistogram(
            '',
            $this->name,
            $this->description,
            $labelNames,
            $attributes['buckets'] ?? Histogram::getDefaultBuckets(),
        );
        $histogram->observe($amount, $labelValues);
    }

    public function isEnabled(): bool
    {
        return true;
    }
}
