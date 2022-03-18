<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Instrumentation;

use OpenTelemetry\API\Trace\NoopTracer;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\SDK\Trace\TracerProvider;

final class TogglableTracerProvider implements TracerProviderInterface
{
    private bool $enabled = true;

    /** @param TracerProvider $decorated */
    public function __construct(private TracerProviderInterface $decorated)
    {
    }

    public function getTracer(string $name, ?string $version = null): TracerInterface
    {
        if (!$this->isEnabled()) {
            return NoopTracer::getInstance();
        }

        return $this->decorated->getTracer($name, $version);
    }

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function forceFlush(): ?bool
    {
        return $this->decorated->forceFlush();
    }
}
