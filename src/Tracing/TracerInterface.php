<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface as BaseTracerInterface;
use OpenTelemetry\Context\Context;

interface TracerInterface extends BaseTracerInterface
{
    /**
     * @param non-empty-string     $operation
     * @param array<string,string> $attributes
     * @param SpanKind::KIND_*     $kind
     */
    public function trace(string $operation, array|null $attributes = null, int|null $kind = null, Context|null $parentContext = null): SpanInterface;
}
