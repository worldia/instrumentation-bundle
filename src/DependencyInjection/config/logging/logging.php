<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Resources;

use Instrumentation\Logging;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set(Logging\Logging::class)
        ->args([service(LoggerInterface::class)])
        ->lazy(false)
        ->public()

        ->set(Logging\Formatter\JsonFormatter::class)
        ->alias('monolog.formatter.json', Logging\Formatter\JsonFormatter::class)

        ->set(Logging\Processor\TraceContextProcessor::class)
        ->args([param('logging.trace_context_keys')])
        ->tag('monolog.processor');
};
