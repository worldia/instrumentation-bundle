<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\Context;

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

    /**
     * @param non-empty-string&string $name
     * @param array<string,string>    $attributes
     * @param SpanKind::KIND_*        $kind
     */
    protected function traceFunction(string $name, array $attributes, callable $callback, Context|null $parentContext = null, int|null $kind = null): mixed
    {
        $span = $this->getTracer()
            ->spanBuilder($name) // @phpstan-ignore-line
            ->setSpanKind($kind ?: SpanKind::KIND_SERVER)
            ->setParent($parentContext ?: Context::getCurrent())
            ->setAttributes($attributes)
            ->startSpan();

        try {
            return $callback();
        } finally {
            $span->end();
        }
    }
}
