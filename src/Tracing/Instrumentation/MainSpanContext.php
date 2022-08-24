<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Instrumentation;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\SDK\Trace\Span;

final class MainSpanContext implements MainSpanContextInterface
{
    private ?SpanInterface $mainSpan = null;

    public function setCurrent(): void
    {
        $this->mainSpan = Span::getCurrent();
    }

    public function setMainSpan(SpanInterface $span): void
    {
        $this->mainSpan = $span;
    }

    public function getMainSpan(): SpanInterface
    {
        if (!$this->mainSpan) {
            return Span::getCurrent();
        }

        return $this->mainSpan;
    }
}
