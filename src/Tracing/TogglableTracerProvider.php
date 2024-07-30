<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing;

use OpenTelemetry\API\Trace\NoopTracer;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\SDK\Sdk;

class TogglableTracerProvider implements TracerProviderInterface
{
    public function __construct(private TracerProviderInterface $decorated)
    {
    }

    /**
     * @param array<non-empty-string,mixed> $attributes
     */
    public function getTracer(string $name, string|null $version = null, string|null $schemaUrl = null, iterable $attributes = []): TracerInterface
    {
        if (Sdk::isDisabled()) {
            return new NoopTracer();
        }

        return $this->decorated->getTracer($name, $version, $schemaUrl, $attributes);
    }
}
