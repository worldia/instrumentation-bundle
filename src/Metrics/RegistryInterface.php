<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Metrics;

use Prometheus\Counter;
use Prometheus\Gauge;
use Prometheus\Histogram;

interface RegistryInterface
{
    public function getCounter(string $name): Counter;

    public function getGauge(string $name): Gauge;

    public function getHistogram(string $name): Histogram;

    /**
     * @param array<string> $labels
     */
    public function createHistogramEvent(string $name, array $labels): HistogramEvent;
}
