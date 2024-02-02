<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Propagation;

use Instrumentation\Tracing\Propagation\Messenger\TraceContextStamp;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\Context\ScopeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Envelope;

final class ContextInitializer
{
    public static function fromRequest(Request $request): ?ScopeInterface
    {
        if (!$traceparent = $request->headers->get(TraceContextPropagator::TRACEPARENT)) {
            return null;
        }

        $tracestate = $request->headers->get(TraceContextPropagator::TRACESTATE);

        return static::activateContext($traceparent, $tracestate);
    }

    public static function fromMessage(Envelope $envelope): ?ScopeInterface
    {
        /** @var TraceContextStamp|null $stamp */
        $stamp = $envelope->last(TraceContextStamp::class);

        if (!$stamp) {
            return null;
        }

        return static::activateContext($stamp->getTraceParent(), $stamp->getTraceState());
    }

    public static function fromW3CHeader(string $header): ScopeInterface
    {
        return static::activateContext($header);
    }

    public static function activateContext(string $parent, ?string $state = null): ScopeInterface
    {
        $context = TraceContextPropagator::getInstance()->extract(array_filter([
            TraceContextPropagator::TRACEPARENT => $parent,
            TraceContextPropagator::TRACESTATE => $state,
        ]));

        return $context->activate();
    }
}
