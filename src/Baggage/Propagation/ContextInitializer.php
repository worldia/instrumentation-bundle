<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Baggage\Propagation;

use Instrumentation\Baggage\Propagation\Messenger\BaggageStamp;
use OpenTelemetry\API\Baggage\Propagation\BaggagePropagator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Envelope;

final class ContextInitializer
{
    public static function fromRequest(Request $request): void
    {
        if (!$baggage = $request->headers->get(BaggagePropagator::BAGGAGE)) {
            return;
        }

        static::activateContext($baggage);
    }

    public static function fromMessage(Envelope $envelope): void
    {
        /** @var BaggageStamp|null $stamp */
        $stamp = $envelope->last(BaggageStamp::class);

        if (!$stamp) {
            return;
        }

        static::activateContext($stamp->getBaggage());
    }

    public static function fromW3CHeader(string $header): void
    {
        static::activateContext($header);
    }

    public static function activateContext(string $baggage): void
    {
        $context = BaggagePropagator::getInstance()->extract(array_filter([
            BaggagePropagator::BAGGAGE => $baggage,
        ]));

        $context->activate();
    }
}
