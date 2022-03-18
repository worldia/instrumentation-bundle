<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation;

use Instrumentation\Tracing\TracerInterface;
use Psr\Log\LoggerInterface;

final class Tools
{
    public static function tracer(): TracerInterface
    {
        return Tracing\Tracing::getTracer();
    }

    public static function logger(): LoggerInterface
    {
        return Logging\Logging::getLogger();
    }
}
