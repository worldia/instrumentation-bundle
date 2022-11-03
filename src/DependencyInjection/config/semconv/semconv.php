<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Resources;

use Instrumentation\Semantics\Attribute\DoctrineConnectionAttributeProvider;
use Instrumentation\Semantics\Attribute\DoctrineConnectionAttributeProviderInterface;
use Instrumentation\Semantics\Attribute\MessageAttributeProvider;
use Instrumentation\Semantics\Attribute\MessageAttributeProviderInterface;
use Instrumentation\Semantics\Attribute\ServerRequestAttributeProvider;
use Instrumentation\Semantics\Attribute\ServerRequestAttributeProviderInterface;
use Instrumentation\Semantics\Attribute\ServerResponseAttributeProvider;
use Instrumentation\Semantics\Attribute\ServerResponseAttributeProviderInterface;
use Instrumentation\Semantics\OperationName\ClientRequestOperationNameResolver;
use Instrumentation\Semantics\OperationName\ClientRequestOperationNameResolverInterface;
use Instrumentation\Semantics\OperationName\CommandOperationNameResolver;
use Instrumentation\Semantics\OperationName\CommandOperationNameResolverInterface;
use Instrumentation\Semantics\OperationName\MessageOperationNameResolver;
use Instrumentation\Semantics\OperationName\MessageOperationNameResolverInterface;
use Instrumentation\Semantics\OperationName\RoutePath\RouteCacheWarmer;
use Instrumentation\Semantics\OperationName\RoutePath\RoutePathResolver;
use Instrumentation\Semantics\OperationName\RoutePath\RoutePathResolverInterface;
use Instrumentation\Semantics\OperationName\RoutePathServerRequestOperationNameResolver;
use Instrumentation\Semantics\OperationName\ServerRequestOperationNameResolverInterface;
use Instrumentation\Semantics\ResourceInfoProvider;
use Instrumentation\Semantics\ResourceInfoProviderInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

use Symfony\Component\Routing\RouterInterface;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set(ResourceInfoProviderInterface::class, ResourceInfoProvider::class)
        ->args([
            param('app.resource_info'),
        ])

        ->set(ResourceInfo::class)
        ->factory([service(ResourceInfoProviderInterface::class), 'getInfo'])

        ->set(ServerRequestAttributeProviderInterface::class, ServerRequestAttributeProvider::class)
        ->args([
            param('tracing.request.attributes.server_name'),
            param('tracing.request.attributes.headers'),
        ])
        ->set(ServerResponseAttributeProviderInterface::class, ServerResponseAttributeProvider::class)
        ->set(MessageAttributeProviderInterface::class, MessageAttributeProvider::class)
        ->set(DoctrineConnectionAttributeProviderInterface::class, DoctrineConnectionAttributeProvider::class)

        ->set(ClientRequestOperationNameResolverInterface::class, ClientRequestOperationNameResolver::class)
        ->set(MessageOperationNameResolverInterface::class, MessageOperationNameResolver::class)
        ->set(CommandOperationNameResolverInterface::class, CommandOperationNameResolver::class)
        ->set(ServerRequestOperationNameResolverInterface::class, RoutePathServerRequestOperationNameResolver::class)
        ->args([
            service(RoutePathResolverInterface::class),
        ])

        ->set(RouteCacheWarmer::class)
        ->args([
            service(RouterInterface::class),
        ])
        ->autoconfigure()

        ->set(RoutePathResolverInterface::class, RoutePathResolver::class)
        ->args([
            service(RouteCacheWarmer::class),
            param('kernel.cache_dir'),
        ])
        ->autoconfigure()
    ;
};
