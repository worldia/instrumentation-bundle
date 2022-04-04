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
use Instrumentation\Semantics\ResourceInfoProvider;
use Instrumentation\Semantics\ResourceInfoProviderInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

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
        ->set(DoctrineConnectionAttributeProviderInterface::class, DoctrineConnectionAttributeProvider::class);
};
