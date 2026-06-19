<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

use Instrumentation\Tracing\AI\Agent\TracingAgent;
use Instrumentation\Tracing\AI\Platform\TracingPlatform;
use Instrumentation\Tracing\AI\Toolbox\TracingToolbox;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set(TracingPlatform::class)
        ->abstract()
        ->set(TracingAgent::class)
        ->abstract()
        ->set(TracingToolbox::class)
        ->abstract();
};
