<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Resources;

use Instrumentation\Semantics\Attribute\DoctrineConnectionAttributeProviderInterface;
use Instrumentation\Tracing;
use Instrumentation\Tracing\Instrumentation\MainSpanContextInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set(Tracing\Instrumentation\Doctrine\DBAL\Middleware::class)
        ->args([
            service(TracerProviderInterface::class),
            service(DoctrineConnectionAttributeProviderInterface::class),
            service(MainSpanContextInterface::class),
            param('tracing.doctrine.log_queries'),
        ])

        ->set(Tracing\Propagation\Doctrine\DBAL\Middleware::class)
        ->args([
            service(Tracing\Propagation\Doctrine\TraceContextInfoProviderInterface::class),
        ])

        ->set(Tracing\Propagation\Doctrine\TraceContextInfoProviderInterface::class, Tracing\Propagation\Doctrine\TraceContextInfoProvider::class)
        ->args([
            service(MainSpanContextInterface::class),
            param('service.name'),
        ])
        ->public()
    ;
};
