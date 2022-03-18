<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing;

use OpenTelemetry\API\Trace\TracerProviderInterface;

final class Tracing
{
    private static ?TracerProviderInterface $tracerProvider = null;

    public static function getTracer(): TracerInterface
    {
        return new Tracer(self::getProvider()->getTracer('io.opentelemetry.contrib.php'));
    }

    public static function setProvider(TracerProviderInterface $tracerProvider): void
    {
        self::$tracerProvider = $tracerProvider;
    }

    private static function getProvider(): TracerProviderInterface
    {
        if (!self::$tracerProvider) {
            throw new \RuntimeException('No trace provider was set.');
        }

        return self::$tracerProvider;
    }
}
