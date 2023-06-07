<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\Normalizer;

class ExceptionNormalizer
{
    /**
     * @return array{type:string,message:string}
     */
    public static function normalizeException(\Throwable $exception): array
    {
        return [
            'type' => $exception::class,
            'message' => 'PHP Warning: '.(string) $exception,
            // Stacktrace is already included in message
            // 'stacktrace' => $exception->getTraceAsString(),
        ];
    }
}
