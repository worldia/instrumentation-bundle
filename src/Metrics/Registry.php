<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Metrics;

use Prometheus\CollectorRegistry;
use Prometheus\Counter;
use Prometheus\Gauge;
use Prometheus\Histogram;

class Registry implements RegistryInterface
{
    /**
     * @var array<Metric>
     */
    private array $instantiated = [];

    /**
     * @param array<string,array{
     *           type:string,
     *           help:string,
     *           labels?:array<string>,
     *           buckets?:array<int|float>|null
     *         }> $metrics
     */
    public function __construct(private CollectorRegistry $registry, private string $namespace, private array $metrics)
    {
    }

    public function getCounter(string $name): Counter
    {
        $metric = $this->getMetric($name);

        return $this->registry->getOrRegisterCounter($metric->getNamespace(), $metric->getName(), $metric->getHelp(), $metric->getLabels());
    }

    public function getGauge(string $name): Gauge
    {
        $metric = $this->getMetric($name);

        return $this->registry->getOrRegisterGauge($metric->getNamespace(), $metric->getName(), $metric->getHelp(), $metric->getLabels());
    }

    public function getHistogram(string $name): Histogram
    {
        $metric = $this->getMetric($name);

        return $this->registry->getOrRegisterHistogram($metric->getNamespace(), $metric->getName(), $metric->getHelp(), $metric->getLabels(), $metric->getBuckets());
    }

    public function createHistogramEvent(string $name, array $labels): HistogramEvent
    {
        return new HistogramEvent($labels, $this->getHistogram($name));
    }

    private function getMetric(string $name): Metric
    {
        if (!isset($this->instantiated[$name])) {
            if (!isset($this->metrics[$name])) {
                throw new \InvalidArgumentException(sprintf('No metric registered with that name: "%s".', $name));
            }

            $config = $this->metrics[$name];

            $config['name'] = $name;
            $config['namespace'] = $this->namespace;

            $this->instantiated[$name] = new Metric(...$config);
        }

        return $this->instantiated[$name];
    }
}
