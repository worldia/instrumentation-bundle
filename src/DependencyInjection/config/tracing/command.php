<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Resources;

use Instrumentation\Semantics\OperationName\CommandOperationNameResolverInterface;
use Instrumentation\Tracing;
use Instrumentation\Tracing\Instrumentation\MainSpanContextInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set(Tracing\Instrumentation\EventSubscriber\CommandEventSubscriber::class)
        ->args([
            service(TracerProviderInterface::class),
            service(MainSpanContextInterface::class),
            service(CommandOperationNameResolverInterface::class),
        ])
        ->autoconfigure();
};
