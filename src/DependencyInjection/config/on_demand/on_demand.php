<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

use Instrumentation\OnDemand\OnDemandTrigger;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set(OnDemandTrigger::class)
        ->autoconfigure()
        ->args([
            param('on_demand.token'),
            param('on_demand.header'),
        ])
    ;
};
