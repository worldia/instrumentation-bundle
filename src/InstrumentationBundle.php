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
    public function __construct()
    {
        $this->container = null;
    }

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
            $container = $this->container;
            // Hand the facade a lazy closure so the tracer provider (and its OTLP exporter)
            // is only built when a span is actually created — not on every kernel boot.
            Tracing\Tracing::setProvider(static function () use ($container): TracerProviderInterface {
                /** @var TracerProviderInterface $provider */
                $provider = $container->get(TracerProviderInterface::class);

                return $provider;
            });
        }
    }

    public function __destruct()
    {
        if (null === $this->container) {
            return;
        }

        $container = $this->container;

        foreach ([
            TracerProviderInterface::class,
            MeterProviderInterface::class,
            LoggerProviderInterface::class,
        ] as $interface) {
            // Only shut down providers that were actually built this process: never
            // force-instantiate (and lazily build an exporter for) an unused provider.
            if (!$container->initialized($interface)) {
                continue;
            }

            try {
                $provider = $container->get($interface);

                if (method_exists($provider, 'shutdown')) {
                    $provider->shutdown();
                }
            } catch (\Throwable $e) {
                // A destructor must never throw: teardown is best-effort.
                $this->logTeardownFailure($interface, $e);
            }
        }
    }

    private function logTeardownFailure(string $provider, \Throwable $e): void
    {
        try {
            if (null === $this->container
                || !$this->container->has(Logging\Logging::class)
                || !$this->container->initialized(Logging\Logging::class)
            ) {
                return;
            }

            Logging\Logging::getLogger()->error('Failed to shut down telemetry provider during teardown.', [
                'provider' => $provider,
                'exception' => $e,
            ]);
        } catch (\Throwable) {
            // Teardown is best-effort: swallow any logging failure so nothing escapes the destructor.
        }
    }
}
