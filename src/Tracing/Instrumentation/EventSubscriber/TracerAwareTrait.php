<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Instrumentation\EventSubscriber;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;

trait TracerAwareTrait
{
    protected TracerProviderInterface $tracerProvider;

    protected function getTracer(): TracerInterface
    {
        return $this->tracerProvider->getTracer('io.opentelemetry.contrib.php');
    }

    /**
     * @param non-empty-string&string $name
     * @param array<string,string>    $attributes
     */
    protected function startSpan(string $name, array $attributes = []): SpanInterface
    {
        return $this->getTracer()->spanBuilder($name)->setAttributes($attributes)->startSpan();
    }
}
