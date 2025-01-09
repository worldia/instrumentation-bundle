<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

use Instrumentation\Metrics;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set(Metrics\EventSubscriber\MessageEventSubscriber::class)
        ->autoconfigure()
        ->args([
            service(MeterProviderInterface::class),
        ])
        ->set(Metrics\EventSubscriber\ConsumerEventSubscriber::class)
        ->autoconfigure()
        ->args([
            service(MeterProviderInterface::class),
        ])
    ;
};
