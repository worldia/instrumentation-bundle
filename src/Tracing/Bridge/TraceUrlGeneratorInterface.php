<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Bridge;

interface TraceUrlGeneratorInterface
{
    public function getTraceUrl(string $traceId): string;
}
