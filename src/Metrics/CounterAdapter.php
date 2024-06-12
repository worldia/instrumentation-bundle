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
    use IterableAttributesTrait;

    public function __construct(
        private string $name,
        private string $description,
        private CollectorRegistry $collectorRegistry,
    ) {
    }

    /**
     * @param int                                  $amount
     * @param iterable<string,array<string,mixed>> $attributes
     */
    public function add($amount, iterable $attributes = [], $context = null): void
    {
        $attributes = $this->normalizeAttributes($attributes);
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
}
