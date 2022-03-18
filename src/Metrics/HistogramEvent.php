<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Metrics;

use Prometheus\Histogram;

class HistogramEvent
{
    private float $startTime;
    private bool $recorded = false;

    /**
     * @param array<string> $labels
     */
    public function __construct(private array $labels, private Histogram $histogram)
    {
        $this->startTime = microtime(true);
    }

    public function record(): void
    {
        $time = microtime(true) - $this->startTime;
        $this->histogram->observe($time, $this->labels);
        $this->recorded = true;
    }

    public function __destruct()
    {
        if (!$this->recorded) {
            $this->record();
        }
    }
}
