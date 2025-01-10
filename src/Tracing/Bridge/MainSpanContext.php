<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Bridge;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\SDK\Trace\Span;

final class MainSpanContext implements MainSpanContextInterface
{
    private SpanInterface|null $mainSpan = null;
    private string|null $operationName = null;

    public function setCurrent(): void
    {
        $this->mainSpan = Span::getCurrent();
    }

    public function getMainSpan(): SpanInterface
    {
        if (!$this->mainSpan) {
            return Span::getCurrent();
        }

        return $this->mainSpan;
    }

    public function setMainSpan(SpanInterface $span): void
    {
        $this->mainSpan = $span;
    }

    public function getOperationName(): string|null
    {
        return $this->operationName;
    }

    public function setOperationName(string|null $name): void
    {
        $this->operationName = $name;
    }
}
