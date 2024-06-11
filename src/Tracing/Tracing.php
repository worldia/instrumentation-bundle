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
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\NoopTracerProvider;

final class Tracing
{
    public const NAME = 'io.opentelemetry.contrib.php';

    private static TracerProviderInterface|null $tracerProvider = null;

    /**
     * @param non-empty-string     $operation
     * @param array<string,string> $attributes
     * @param SpanKind::KIND_*     $kind
     */
    public static function trace(string $operation, array|null $attributes = null, int|null $kind = null, Context|null $parentContext = null): SpanInterface
    {
        return static::getTracer()->spanBuilder($operation)
            ->setAttributes($attributes ?: [])
            ->setSpanKind($kind ?: SpanKind::KIND_SERVER)
            ->setParent($parentContext ?: Context::getCurrent())
            ->startSpan();
    }

    public static function getTracer(): TracerInterface
    {
        return new Tracer(self::getProvider()->getTracer(self::NAME));
    }

    public static function setProvider(TracerProviderInterface $tracerProvider): void
    {
        self::$tracerProvider = $tracerProvider;
    }

    private static function getProvider(): TracerProviderInterface
    {
        if (Sdk::isDisabled()) {
            return new NoopTracerProvider();
        }
        if (!self::$tracerProvider) {
            throw new \RuntimeException('No trace provider was set.');
        }

        return self::$tracerProvider;
    }
}
