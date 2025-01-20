<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation;

use OpenTelemetry\API\Logs\LoggerProviderInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class InstrumentationBundle extends Bundle
{
    public function getContainerExtensionClass(): string
    {
        return DependencyInjection\Extension::class;
    }

    public function boot(): void
    {
        if (null === $this->container) {
            return;
        }

        if ($this->container->has(Logging\Logging::class)) {
            $this->container->get(Logging\Logging::class);
        }

        if ($this->container->has(TracerProviderInterface::class)) {
            /** @var TracerProviderInterface $tracerProvider */
            $tracerProvider = $this->container->get(TracerProviderInterface::class);
            Tracing\Tracing::setProvider($tracerProvider);
        }
    }

    public function __destruct()
    {
        foreach ([
            TracerProviderInterface::class,
            MeterProviderInterface::class,
            LoggerProviderInterface::class,
        ] as $interface) {
            if ($this->container->has($interface)) {
                $provider = $this->container->get($interface);

                if (method_exists($provider, 'shutdown')) {
                    $provider->shutdown();
                }
            }
        }
    }
}
