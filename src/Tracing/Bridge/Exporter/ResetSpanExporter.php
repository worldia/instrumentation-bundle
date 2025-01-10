<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Bridge\Exporter;

use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use Symfony\Contracts\Service\ResetInterface;

final class ResetSpanExporter implements ResetInterface
{
    public function __construct(private SpanExporterInterface $spanExporter)
    {
    }

    public function reset(): void
    {
        $this->spanExporter->forceFlush();
    }
}
