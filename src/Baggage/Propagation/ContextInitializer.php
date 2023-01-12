<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Baggage\Propagation;

use Instrumentation\Baggage\Propagation\Messenger\BaggageStamp;
use OpenTelemetry\API\Baggage\Propagation\BaggagePropagator;
use OpenTelemetry\Context\ScopeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Envelope;

final class ContextInitializer
{
    public static function fromRequest(Request $request): ?ScopeInterface
    {
        if (!$baggage = $request->headers->get(BaggagePropagator::BAGGAGE)) {
            return null;
        }

        return static::activateContext($baggage);
    }

    public static function fromMessage(Envelope $envelope): ?ScopeInterface
    {
        /** @var BaggageStamp|null $stamp */
        $stamp = $envelope->last(BaggageStamp::class);

        if (!$stamp) {
            return null;
        }

        return static::activateContext($stamp->getBaggage());
    }

    public static function fromW3CHeader(string $header): ?ScopeInterface
    {
        return static::activateContext($header);
    }

    public static function activateContext(string $baggage): ScopeInterface
    {
        $context = BaggagePropagator::getInstance()->extract(array_filter([
            BaggagePropagator::BAGGAGE => $baggage,
        ]));

        return $context->activate();
    }
}
