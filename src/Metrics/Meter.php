<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Metrics;

use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Metrics\ObservableCounterInterface;
use OpenTelemetry\API\Metrics\ObservableGaugeInterface;
use OpenTelemetry\API\Metrics\ObservableUpDownCounterInterface;
use OpenTelemetry\API\Metrics\UpDownCounterInterface;
use Prometheus\CollectorRegistry;

class Meter implements MeterInterface
{
    public function __construct(
        private CollectorRegistry $collectorRegistry,
    ) {
    }

    public function createCounter(string $name, string $unit = null, string $description = null, array $advisory = []): CounterInterface
    {
        return new CounterAdapter($name, $description ?: '', $this->collectorRegistry);
    }

    public function createObservableCounter(string $name, string $unit = null, string $description = null, callable ...$callbacks): ObservableCounterInterface
    {
        throw new \LogicException(sprintf('Method %s is not implemented', __METHOD__));
    }

    public function createHistogram(string $name, string $unit = null, string $description = null, array $advisory = []): HistogramInterface
    {
        return new HistogramAdapter($name, $description ?: '', $this->collectorRegistry);
    }

    public function createObservableGauge(string $name, string $unit = null, string $description = null, $advisory = [], callable ...$callbacks): ObservableGaugeInterface
    {
        throw new \LogicException(sprintf('Method %s is not implemented', __METHOD__));
    }

    public function createUpDownCounter(string $name, string $unit = null, string $description = null, array $advisory = []): UpDownCounterInterface
    {
        return new UpDownCounterAdapter($name, $description ?: '', $this->collectorRegistry);
    }

    public function createObservableUpDownCounter(string $name, string $unit = null, string $description = null, $advisory = [], callable ...$callbacks): ObservableUpDownCounterInterface
    {
        throw new \LogicException(sprintf('Method %s is not implemented', __METHOD__));
    }
}
