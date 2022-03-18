<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Baggage;

use OpenTelemetry\API\Baggage\Baggage as OTBaggage;
use OpenTelemetry\API\Baggage\Metadata;
use OpenTelemetry\API\Baggage\MetadataInterface;

class Baggage
{
    /**
     * @param string $value
     */
    public static function set(string $key, $value, MetadataInterface|string $metadata = null): void
    {
        if (\is_string($metadata)) {
            $metadata = new Metadata($metadata);
        }

        OTBaggage::getCurrent()->toBuilder()->set($key, $value, $metadata)->build()->activate();
    }
}
