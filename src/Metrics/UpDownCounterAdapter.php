<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Metrics;

use OpenTelemetry\API\Metrics\UpDownCounterInterface;
use Prometheus\CollectorRegistry;

class UpDownCounterAdapter implements UpDownCounterInterface
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

        $gauge = $this->collectorRegistry->getOrRegisterGauge(
            '',
            $this->name,
            $this->description,
            $labelNames,
        );

        if ($amount > 0) {
            $gauge->incBy($amount, $labelValues);
        } else {
            $gauge->decBy(abs($amount), $labelValues);
        }
    }
}
