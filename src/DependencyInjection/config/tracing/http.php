<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

use Instrumentation\Semantics\Attribute\ClientRequestAttributeProviderInterface;
use Instrumentation\Semantics\OperationName\ClientRequestOperationNameResolverInterface;
use Instrumentation\Tracing\HttpClient\Propagation\TraceParentHeaderProvider;
use Instrumentation\Tracing\HttpClient\Propagation\TraceStateHeaderProvider;
use Instrumentation\Tracing\HttpClient\TracingHttpClient;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set(TraceParentHeaderProvider::class)
        ->set(TraceStateHeaderProvider::class)
    ;

    if (interface_exists(HttpClientInterface::class)) {
        $container->services()
            ->set(TracingHttpClient::class)
            ->decorate(HttpClientInterface::class, null, 255)
            ->args([
                service('.inner'),
                service(ClientRequestOperationNameResolverInterface::class),
                service(ClientRequestAttributeProviderInterface::class),
                param('tracing.http.propagate_by_default'),
            ])
        ;
    }
};
