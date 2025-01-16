<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Bridge\Profiler\DataCollector;

use Instrumentation\Tracing\Bridge\TraceUrlGeneratorInterface;
use OpenTelemetry\API\Trace\LocalRootSpan;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScope;
use OpenTelemetry\SDK\Trace\ReadableSpanInterface;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class TraceContextDataCollector extends AbstractDataCollector
{
    public function __construct(private TraceUrlGeneratorInterface|null $traceUrlGenerator = null)
    {
    }

    public function collect(Request $request, Response $response, \Throwable|null $exception = null): void
    {
        $rootSpan = LocalRootSpan::current();

        $attributes = [];
        $instrumentationScope = null;

        if ($rootSpan instanceof ReadableSpanInterface) {
            $spanData = $rootSpan->toSpanData();
            $instrumentationScope = $spanData->getInstrumentationScope();
            $attributes = $spanData->getAttributes()->toArray();

            foreach ($attributes as $attribute => $value) {
                if (\is_array($value)) {
                    $attributes[$attribute] = implode(',', $value);
                }
            }
        }

        $this->data = [
            'instrumentation_scope' => $instrumentationScope,
            'trace_id' => $rootSpan->getContext()->getTraceId(),
            'root_span' => $rootSpan,
            'attributes' => $attributes,
            'trace_url' => $this->traceUrlGenerator?->getTraceUrl($rootSpan->getContext()->getTraceId()),
        ];
    }

    public function getName(): string
    {
        return self::class;
    }

    public function getTraceId(): string
    {
        return $this->data['trace_id'];
    }

    public function getRootSpan(): SpanInterface
    {
        return $this->data['root_span'];
    }

    public function getTraceUrl(): string|null
    {
        return $this->data['trace_url'];
    }

    /**
     * @return array<string,string>
     */
    public function getAttributes(): array
    {
        return $this->data['attributes'];
    }

    public function getInstrumentationScope(): InstrumentationScope|null
    {
        return $this->data['instrumentation_scope'];
    }

    public static function getTemplate(): string|null
    {
        return '@InstrumentationDataCollector/collector.html.twig';
    }
}
