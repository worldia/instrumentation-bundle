<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Baggage\Propagation\Messenger;

use OpenTelemetry\API\Baggage\Propagation\BaggagePropagator;
use Symfony\Component\Messenger\Stamp\StampInterface;

final class BaggageStamp implements StampInterface
{
    private string $baggage;

    public function __construct()
    {
        $baggageContext = [];
        BaggagePropagator::getInstance()->inject($baggageContext);

        $this->baggage = $baggageContext[BaggagePropagator::BAGGAGE] ?? '';
    }

    public function getBaggage(): string
    {
        return $this->baggage;
    }
}
