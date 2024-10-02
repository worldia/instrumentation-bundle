<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Metrics;

use OpenTelemetry\API\Metrics\CounterInterface;
use Prometheus\CollectorRegistry;

class CounterAdapter implements CounterInterface
{
    public function __construct(
        private string $name,
        private string $description,
        private CollectorRegistry $collectorRegistry,
    ) {
    }

    /**
     * @param int                                $amount
     * @param array{labels: array<string,mixed>} $attributes
     */
    public function add($amount, iterable $attributes = [], $context = null): void
    {
        if (!\is_array($attributes)) {
            return;
        }

        /** @var array<string> $labelNames */
        $labelNames = array_keys($attributes['labels'] ?? []);
        $labelValues = array_values($attributes['labels'] ?? []);

        $counter = $this->collectorRegistry->getOrRegisterCounter(
            '',
            $this->name,
            $this->description,
            $labelNames,
        );

        $counter->incBy($amount, $labelValues);
    }

    public function isEnabled(): bool
    {
        return true;
    }
}
