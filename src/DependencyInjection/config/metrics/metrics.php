<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\SDK\Metrics\MeterProviderFactory;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set(MeterProviderFactory::class)
        ->args([
            null,
            service(ResourceInfo::class),
        ])
        ->set(MeterProviderInterface::class)
        ->factory([service(MeterProviderFactory::class), 'create'])
        ->args(['$resource' => service(ResourceInfo::class)])
        ->lazy(false)
        ->public()
    ;
};
