<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

use Instrumentation\Metrics;
use Instrumentation\Tracing\Bridge\MainSpanContextInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set(Metrics\EventSubscriber\RequestEventSubscriber::class)
        ->autoconfigure()
        ->args([
            service(MeterProviderInterface::class),
            param('metrics.request.blacklist'),
            service(MainSpanContextInterface::class)->nullOnInvalid(),
        ])
    ;
};
