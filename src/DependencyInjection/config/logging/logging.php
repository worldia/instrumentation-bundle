<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

use Instrumentation\Logging;
use Monolog\Level;
use OpenTelemetry\API\Logs\LoggerProviderInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\SDK\Logs\LoggerProviderFactory;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set(Logging\Logging::class)
        ->args([service(LoggerInterface::class)])
        ->lazy(false)
        ->public()

        ->set(LoggerProviderFactory::class)
        ->args([
            service(MeterProviderInterface::class)->nullOnInvalid(),
            service(ResourceInfo::class),
        ])
        ->set(LoggerProviderInterface::class)
        ->factory([service(LoggerProviderFactory::class), 'create'])
        ->args(['$resource' => service(ResourceInfo::class)])
        ->lazy(false)
        ->public()

        ->set(Logging\OtelHandler::class)
        ->args([
            '$loggerProvider' => service(LoggerProviderInterface::class),
            '$level' => Level::Debug,
            '$bubble' => true,
        ])
        ->lazy(false)
        ->public()
    ;
};
