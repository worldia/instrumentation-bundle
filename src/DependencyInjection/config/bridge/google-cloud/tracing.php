<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Resources;

use Instrumentation\Bridge\GoogleCloud\Tracing\Propagation\EventSubscriber\TraceContextSubscriber;
use Instrumentation\Bridge\GoogleCloud\Tracing\TraceUrlGenerator;
use Instrumentation\Tracing\TraceUrlGeneratorInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\env;

return static function (ContainerConfigurator $container) {
    $container->parameters()
        ->set('env(GCP_PROJECT)', 'local')
    ;

    $container->services()
        ->set(TraceContextSubscriber::class)
        ->autowire()
        ->autoconfigure()

        ->set(TraceUrlGeneratorInterface::class, TraceUrlGenerator::class)
        ->args([
            env('GCP_PROJECT'),
        ])
    ;
};
