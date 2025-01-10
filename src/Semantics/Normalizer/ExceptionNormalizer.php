<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\Normalizer;

use OpenTelemetry\SemConv\TraceAttributes;

class ExceptionNormalizer
{
    /**
     * @see https://opentelemetry.io/docs/specs/semconv/exceptions/
     *
     * @return array{type:string,message:string}
     */
    public static function normalizeException(\Throwable $exception): array
    {
        return [
            TraceAttributes::EXCEPTION_TYPE => $exception::class,
            TraceAttributes::EXCEPTION_MESSAGE => $exception->getMessage(),
            TraceAttributes::EXCEPTION_STACKTRACE => $exception->getTraceAsString(),
        ];
    }
}
