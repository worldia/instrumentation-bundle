<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

use Instrumentation\Http\TracingHttpClient;
use Instrumentation\Tracing\Propagation\Http\TraceParentHeaderProvider;
use Instrumentation\Tracing\Propagation\Http\TraceStateHeaderProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set(TraceParentHeaderProvider::class)
        ->set(TraceStateHeaderProvider::class)
    ;

    if (class_exists(HttpClientInterface::class)) {
        $container->services()
            ->set(TracingHttpClient::class)
            ->decorate(HttpClientInterface::class, null, 255)
            ->args([
                service('.inner'),
            ])
        ;
    }
};
