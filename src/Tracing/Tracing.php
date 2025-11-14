<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\SDK\Trace\NoopTracerProvider;

final class Tracing
{
    public const NAME = 'io.opentelemetry.contrib.php.worldia';

    private static TracerProviderInterface|null $tracerProvider = null;

    /**
     * @param non-empty-string     $spanName
     * @param array<string,string> $attributes
     * @param SpanKind::KIND_*     $kind
     */
    public static function trace(string $spanName, array|null $attributes = null, int|null $kind = null, ContextInterface|null $parentContext = null): SpanInterface
    {
        return static::getTracer()->spanBuilder($spanName)
            ->setAttributes($attributes ?: [])
            ->setSpanKind($kind ?: SpanKind::KIND_INTERNAL)
            ->setParent($parentContext ?: Context::getCurrent())
            ->startSpan();
    }

    public static function getTracer(string|null $name = null): TracerInterface
    {
        return new Tracer(self::getProvider()->getTracer($name ?: self::NAME));
    }

    public static function setProvider(TracerProviderInterface $tracerProvider): void
    {
        self::$tracerProvider = $tracerProvider;
    }

    private static function getProvider(): TracerProviderInterface
    {
        return self::$tracerProvider ?: new NoopTracerProvider();
    }
}
