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
use Instrumentation\Semantics\Attribute\RequestAttributeProvider;
use Instrumentation\Semantics\Attribute\RequestAttributeProviderInterface;
use Instrumentation\Semantics\Attribute\ResponseAttributeProvider;
use Instrumentation\Semantics\Attribute\ResponseAttributeProviderInterface;
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

        ->set(RequestAttributeProviderInterface::class, RequestAttributeProvider::class)
        ->set(ResponseAttributeProviderInterface::class, ResponseAttributeProvider::class)
        ->set(MessageAttributeProviderInterface::class, MessageAttributeProvider::class)
        ->set(DoctrineConnectionAttributeProviderInterface::class, DoctrineConnectionAttributeProvider::class);
};
