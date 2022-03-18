<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Resources;

use Instrumentation\Bridge\Jaeger\Tracing\TraceUrlGenerator;
use Instrumentation\Tracing\TraceUrlGeneratorInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\env;

return static function (ContainerConfigurator $container) {
    $container->parameters()
        ->set('env(JAEGER_PUBLIC_URL)', 'http://localhost:16686')
    ;

    $container->services()
        ->set(TraceUrlGeneratorInterface::class, TraceUrlGenerator::class)
        ->args([
            env('JAEGER_PUBLIC_URL'),
        ])
    ;
};
