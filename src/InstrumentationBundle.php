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
use OpenTelemetry\SDK\Common\Util\ShutdownHandler;
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

            /** @var LoggerProviderInterface $loggerProvider */
            $loggerProvider = $this->container->get(LoggerProviderInterface::class);

            if (method_exists($loggerProvider, 'shutdown')) {
                ShutdownHandler::register([$loggerProvider, 'shutdown']);
            }
        }

        if ($this->container->has(TracerProviderInterface::class)) {
            /** @var TracerProviderInterface $tracerProvider */
            $tracerProvider = $this->container->get(TracerProviderInterface::class);
            Tracing\Tracing::setProvider($tracerProvider);

            if (method_exists($tracerProvider, 'shutdown')) {
                ShutdownHandler::register([$tracerProvider, 'shutdown']);
            }
        }

        if ($this->container->has(MeterProviderInterface::class)) {
            /** @var MeterProviderInterface $meterProvider */
            $meterProvider = $this->container->get(MeterProviderInterface::class);

            if (method_exists($meterProvider, 'shutdown')) {
                ShutdownHandler::register([$meterProvider, 'shutdown']);
            }
        }
    }
}
