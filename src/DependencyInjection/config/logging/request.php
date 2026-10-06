<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

use Instrumentation\Logging;
use OpenTelemetry\API\Logs\LoggerProviderInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set(Logging\EventSubscriber\RequestEventSubscriber::class)
        ->args([
            service(LoggerProviderInterface::class),
        ])
        ->autoconfigure()
    ;
};
