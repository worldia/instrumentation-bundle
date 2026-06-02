<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

use Doctrine\DBAL\ServerVersionProvider;
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
    // Pick the connection decorator matching the installed doctrine/dbal major version.
    // ServerVersionProvider only exists in doctrine/dbal 4. This is resolved once, at
    // container compilation time, rather than through reflection on every connection.
    $connectionClass = interface_exists(ServerVersionProvider::class)
        ? Doctrine\Instrumentation\DBAL\Connection::class
        : Doctrine\Instrumentation\DBAL\DBAL3\Connection::class;

    $container->services()
        ->set(Doctrine\Instrumentation\DBAL\Middleware::class)
        ->args([
            service(TracerProviderInterface::class),
            service(DoctrineConnectionAttributeProviderInterface::class),
            service(MainSpanContextInterface::class),
            param('tracing.doctrine.log_queries'),
            $connectionClass,
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
