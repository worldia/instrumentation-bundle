<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Resources;

use Instrumentation\Metrics;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\Adapter;
use Prometheus\Storage\APCng;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set(Metrics\Controller\Endpoint::class)
        ->args([
            service(CollectorRegistry::class),
            service('profiler')->nullOnInvalid(),
        ])
        ->tag('controller.service_arguments')

        ->set(Adapter::class, APCng::class)

        ->set(CollectorRegistry::class)
        ->args([
            service(Adapter::class),
            false,
        ])

        ->set(Metrics\RegistryInterface::class, Metrics\Registry::class)
        ->args([
            service(CollectorRegistry::class),
            param('metrics.namespace'),
            param('metrics.metrics'),
        ])
        ->public()

        ->set(Metrics\EventSubscriber\RequestEventSubscriber::class)
        ->autoconfigure()
        ->args([
            service(Metrics\RegistryInterface::class),
            param('app.path_blacklist'),
        ])

        ->set(Metrics\EventSubscriber\ConsumerEventSubscriber::class)
        ->autoconfigure()
        ->args([
            service(Metrics\RegistryInterface::class),
        ])

        ->set(Metrics\EventSubscriber\MessageEventSubscriber::class)
        ->autoconfigure()
        ->args([
            service(Metrics\RegistryInterface::class),
        ]);
};
