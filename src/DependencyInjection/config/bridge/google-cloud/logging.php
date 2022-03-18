<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Resources;

use Instrumentation\Bridge\GoogleCloud\Logging\Formatter\JsonFormatter;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\env;

return static function (ContainerConfigurator $container) {
    $container->parameters()
        ->set('env(GCP_PROJECT)', 'local')
        ->set('logging.bridge.custom-formatter', JsonFormatter::class)
    ;

    $container->services()
        ->set(JsonFormatter::class)
        ->args([
            env('GCP_PROJECT'),
        ])
    ;
};
