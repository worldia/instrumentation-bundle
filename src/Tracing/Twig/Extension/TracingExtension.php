<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Twig\Extension;

use Instrumentation\Tracing\TraceUrlGeneratorInterface;
use OpenTelemetry\SDK\Trace\Span;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TracingExtension extends AbstractExtension
{
    public function __construct(private ?TraceUrlGeneratorInterface $traceUrlGenerator = null)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_trace_id', [$this, 'getTraceId']),
            new TwigFunction('get_logs_url', [$this, 'getLogsUrl']),
            new TwigFunction('get_trace_url', [$this, 'getTraceUrl']),
        ];
    }

    public function getTraceId(): string
    {
        return Span::getCurrent()->getContext()->getTraceId();
    }

    public function getLogsUrl(string $traceId): ?string
    {
        if ($this->traceUrlGenerator) {
            return $this->traceUrlGenerator->getLogsUrl($traceId);
        }

        return null;
    }

    public function getTraceUrl(string $traceId): ?string
    {
        if ($this->traceUrlGenerator) {
            return $this->traceUrlGenerator->getTraceUrl($traceId);
        }

        return null;
    }
}
