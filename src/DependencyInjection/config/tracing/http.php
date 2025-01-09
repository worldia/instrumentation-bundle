<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

use Instrumentation\Tracing\Propagation\Http\TraceParentHeaderProvider;
use Instrumentation\Tracing\Propagation\Http\TraceStateHeaderProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set(TraceParentHeaderProvider::class)
        ->set(TraceStateHeaderProvider::class)
    ;
};
