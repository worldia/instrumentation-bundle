<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing;

use OpenTelemetry\API\Trace\NoopTracer;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\Context;

final class Tracing
{
    public const NAME = 'io.opentelemetry.contrib.php';

    private static ?TracerProviderInterface $tracerProvider = null;
    private static ?TracerInterface $tracer = null;

    /**
     * @param non-empty-string     $operation
     * @param array<string,string> $attributes
     * @param SpanKind::KIND_*     $kind
     */
    public static function trace(string $operation, ?array $attributes = null, ?int $kind = null, ?Context $parentContext = null): SpanInterface
    {
        return static::getTracer()->spanBuilder($operation)
            ->setAttributes($attributes ?: [])
            ->setSpanKind($kind ?: SpanKind::KIND_SERVER)
            ->setParent($parentContext ?: Context::getCurrent())
            ->startSpan();
    }

    public static function getTracer(): TracerInterface
    {
        if (null === self::$tracer) {
            if(null === $tracer = self::getProvider()?->getTracer(self::NAME)) {
                $tracer = NoopTracer::getInstance();
            }

            self::$tracer = new Tracer($tracer);
        }

        return self::$tracer;
    }

    public static function setProvider(TracerProviderInterface $tracerProvider): void
    {
        self::$tracerProvider = $tracerProvider;
    }

    private static function getProvider(): ?TracerProviderInterface
    {
        return self::$tracerProvider;
    }
}
