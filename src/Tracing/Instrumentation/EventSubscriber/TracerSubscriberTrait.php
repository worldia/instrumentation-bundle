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

trait TracerSubscriberTrait
{
    public function __construct(protected TracerProviderInterface $tracerProvider)
    {
    }

    private function getTracer(): TracerInterface
    {
        return $this->tracerProvider->getTracer('io.opentelemetry.contrib.php');
    }

    /**
     * @param non-empty-string $name
     */
    private function startSpan(string $name): SpanInterface
    {
        return $this->getTracer()->spanBuilder($name)->startSpan();
    }
}
