<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation;

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
        $this->container->get(Logging\Logging::class);

        /** @var TracerProviderInterface $tracerProvider */
        $tracerProvider = $this->container->get(TracerProviderInterface::class);
        Tracing\Tracing::setProvider($tracerProvider);

        if (method_exists($tracerProvider, 'shutdown')) {
            ShutdownHandler::register([$tracerProvider, 'shutdown']);
        }
    }
}
