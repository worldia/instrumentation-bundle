<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Metrics;

use OpenTelemetry\API\Metrics\AsynchronousInstrument;
use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Metrics\ObservableCallbackInterface;
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

    /**
     * Creates a `Counter`.
     *
     * @param string        $name        name of the instrument
     * @param string|null   $unit        unit of measure
     * @param string|null   $description description of the instrument
     * @param array<string> $advisory    an optional set of recommendations
     *
     * @return CounterInterface created instrument
     *
     * @see https://github.com/open-telemetry/opentelemetry-specification/blob/main/specification/metrics/api.md#counter-creation
     */
    public function createCounter(string $name, string|null $unit = null, string|null $description = null, array $advisory = []): CounterInterface
    {
        return new CounterAdapter($name, $description ?: '', $this->collectorRegistry);
    }

    /**
     * Creates an `ObservableCounter`.
     *
     * @param string                 $name         name of the instrument
     * @param string|null            $unit         unit of measure
     * @param string|null            $description  description of the instrument
     * @param array<string>|callable $advisory     an optional set of recommendations, or
     *                                             deprecated: the first callback to report measurements
     * @param callable               ...$callbacks responsible for reporting measurements
     *
     * @return ObservableCounterInterface created instrument
     *
     * @see https://github.com/open-telemetry/opentelemetry-specification/blob/main/specification/metrics/api.md#asynchronous-counter-creation
     */
    public function createObservableCounter(string $name, string|null $unit = null, string|null $description = null, $advisory = [], callable ...$callbacks): ObservableCounterInterface
    {
        throw new \LogicException(\sprintf('Method %s is not implemented', __METHOD__));
    }

    /**
     * Creates a `Histogram`.
     *
     * @param string        $name        name of the instrument
     * @param string|null   $unit        unit of measure
     * @param string|null   $description description of the instrument
     * @param array<string> $advisory    an optional set of recommendations, e.g.
     *                                   <code>['ExplicitBucketBoundaries' => [0.25, 0.5, 1, 5]]</code>
     *
     * @return HistogramInterface created instrument
     *
     * @see https://github.com/open-telemetry/opentelemetry-specification/blob/main/specification/metrics/api.md#histogram-creation
     */
    public function createHistogram(string $name, string|null $unit = null, string|null $description = null, array $advisory = []): HistogramInterface
    {
        return new HistogramAdapter($name, $description ?: '', $this->collectorRegistry);
    }

    /**
     * Creates an `ObservableGauge`.
     *
     * @param string                 $name         name of the instrument
     * @param string|null            $unit         unit of measure
     * @param string|null            $description  description of the instrument
     * @param array<string>|callable $advisory     an optional set of recommendations, or
     *                                             deprecated: the first callback to report measurements
     * @param callable               ...$callbacks responsible for reporting measurements
     *
     * @return ObservableGaugeInterface created instrument
     *
     * @see https://github.com/open-telemetry/opentelemetry-specification/blob/main/specification/metrics/api.md#asynchronous-gauge-creation
     */
    public function createObservableGauge(string $name, string|null $unit = null, string|null $description = null, $advisory = [], callable ...$callbacks): ObservableGaugeInterface
    {
        throw new \LogicException(\sprintf('Method %s is not implemented', __METHOD__));
    }

    /**
     * Creates an `UpDownCounter`.
     *
     * @param string        $name        name of the instrument
     * @param string|null   $unit        unit of measure
     * @param string|null   $description description of the instrument
     * @param array<string> $advisory    an optional set of recommendations
     *
     * @return UpDownCounterInterface created instrument
     *
     * @see https://github.com/open-telemetry/opentelemetry-specification/blob/main/specification/metrics/api.md#updowncounter-creation
     */
    public function createUpDownCounter(string $name, string|null $unit = null, string|null $description = null, array $advisory = []): UpDownCounterInterface
    {
        return new UpDownCounterAdapter($name, $description ?: '', $this->collectorRegistry);
    }

    /**
     * Creates an `ObservableUpDownCounter`.
     *
     * @param string                 $name         name of the instrument
     * @param string|null            $unit         unit of measure
     * @param string|null            $description  description of the instrument
     * @param array<string>|callable $advisory     an optional set of recommendations, or
     *                                             deprecated: the first callback to report measurements
     * @param callable               ...$callbacks responsible for reporting measurements
     *
     * @return ObservableUpDownCounterInterface created instrument
     *
     * @see https://github.com/open-telemetry/opentelemetry-specification/blob/main/specification/metrics/api.md#asynchronous-updowncounter-creation
     */
    public function createObservableUpDownCounter(string $name, string|null $unit = null, string|null $description = null, $advisory = [], callable ...$callbacks): ObservableUpDownCounterInterface
    {
        throw new \LogicException(\sprintf('Method %s is not implemented', __METHOD__));
    }

    public function batchObserve(
        callable $callback,
        AsynchronousInstrument $instrument,
        AsynchronousInstrument ...$instruments
    ): ObservableCallbackInterface {
        throw new \LogicException(\sprintf('Method %s is not implemented', __METHOD__));
    }
}
