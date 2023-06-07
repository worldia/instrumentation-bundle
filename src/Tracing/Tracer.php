<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing;

use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface as BaseTracerInterface;
use OpenTelemetry\Context\Context;

class Tracer implements TracerInterface
{
    public function __construct(private BaseTracerInterface $decorated)
    {
    }

    public function trace(string $operation, array $attributes = null, int $kind = null, Context $parentContext = null): SpanInterface
    {
        return $this->decorated->spanBuilder($operation)
            ->setAttributes($attributes ?: [])
            ->setSpanKind($kind ?: SpanKind::KIND_SERVER)
            ->setParent($parentContext ?: Context::getCurrent())
            ->startSpan();
    }

    /**
     * @param non-empty-string $spanName
     */
    public function spanBuilder(string $spanName): SpanBuilderInterface
    {
        return $this->decorated->spanBuilder($spanName);
    }
}
