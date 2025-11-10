<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\Normalizer;

use OpenTelemetry\SemConv\Attributes\ExceptionAttributes;

class ExceptionNormalizer
{
    /**
     * @see https://opentelemetry.io/docs/specs/semconv/exceptions/
     *
     * @return array{"exception.type":string,"exception.message":string,"exception.stacktrace":string}
     */
    public static function normalizeException(\Throwable $exception): array
    {
        return [
            ExceptionAttributes::EXCEPTION_TYPE => $exception::class,
            ExceptionAttributes::EXCEPTION_MESSAGE => $exception->getMessage(),
            ExceptionAttributes::EXCEPTION_STACKTRACE => (string) $exception,
        ];
    }
}
