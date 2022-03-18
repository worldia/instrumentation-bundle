<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Resources;

use Instrumentation\Health;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set(Health\Controller\Endpoint::class)
        ->args([
            service(ResourceInfo::class),
            tagged_iterator('app.healthcheck'),
            service('profiler')->nullOnInvalid(),
        ])
        ->tag('controller.service_arguments');
};
