<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Baggage\Propagation\Http;

use OpenTelemetry\API\Baggage\Propagation\BaggagePropagator;

class BaggageHeaderProvider implements \Stringable
{
    public static function getHeaderName(): string
    {
        return BaggagePropagator::BAGGAGE;
    }

    public static function getHeaderValue(): string
    {
        $baggageContext = [];
        BaggagePropagator::getInstance()->inject($baggageContext);

        return $baggageContext[BaggagePropagator::BAGGAGE] ?? '';
    }

    public function __toString()
    {
        return $this->getHeaderValue();
    }
}
