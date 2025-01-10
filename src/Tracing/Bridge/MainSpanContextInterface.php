<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Bridge;

use OpenTelemetry\API\Trace\SpanInterface;

interface MainSpanContextInterface
{
    public function setCurrent(): void;

    public function getMainSpan(): SpanInterface;

    public function setMainSpan(SpanInterface $span): void;

    public function getOperationName(): string|null;

    public function setOperationName(string|null $name): void;
}
