<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Resources;

use Instrumentation\Routing\RouteCacheWarmer;
use Instrumentation\Routing\RoutePathResolver;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

use Symfony\Component\Routing\RouterInterface;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set(RouteCacheWarmer::class)
        ->args([
            service(RouterInterface::class),
        ])
        ->autoconfigure()

        ->set(RoutePathResolver::class)
        ->args([
            service(RouteCacheWarmer::class),
            param('kernel.cache_dir'),
        ])
        ->autoconfigure()
    ;
};
