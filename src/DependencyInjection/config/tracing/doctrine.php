<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

use Instrumentation\Semantics\Attribute\DoctrineConnectionAttributeProviderInterface;
use Instrumentation\Tracing\Bridge\MainSpanContextInterface;
use Instrumentation\Tracing\Doctrine;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\RequestStack;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set(Doctrine\Instrumentation\DBAL\Middleware::class)
        ->args([
            service(TracerProviderInterface::class),
            service(DoctrineConnectionAttributeProviderInterface::class),
            service(MainSpanContextInterface::class),
            param('tracing.doctrine.log_queries'),
        ])

        ->set(Doctrine\Propagation\DBAL\Middleware::class)
        ->args([
            service(Doctrine\Propagation\TraceContextInfoProviderInterface::class),
        ])

        ->set(Doctrine\Propagation\TraceContextInfoProviderInterface::class, Doctrine\Propagation\TraceContextInfoProvider::class)
        ->args([
            service(ResourceInfo::class),
            service(MainSpanContextInterface::class),
            service(RequestStack::class)->nullOnInvalid(),
        ])
        ->public()
    ;
};
