<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Instrumentation;

use OpenTelemetry\API\Trace\SpanInterface;

interface MainSpanContextInterface
{
    public function getMainSpan(): SpanInterface;

    public function setMainSpan(SpanInterface $span): void;

    public function setCurrent(): void;
}
