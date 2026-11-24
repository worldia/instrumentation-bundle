<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

use Instrumentation\Profiling;
use Instrumentation\Tracing\Bridge\MainSpanContextInterface;
use Instrumentation\Tracing\Doctrine\Instrumentation\DBAL\QueryTally;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set(Profiling\Encoding\PprofEncoder::class)

        ->set(Profiling\Label\RouteLabelProvider::class)
        ->tag('instrumentation.profiling.label_provider')

        ->set(Profiling\Shipping\PyroscopeShipper::class)
        ->args([
            param('profiling.pyroscope.url'),
            param('profiling.pyroscope.application'),
            param('profiling.pyroscope.environment'),
            param('profiling.pyroscope.upload_deadline_ms'),
            param('profiling.pyroscope.max_in_flight'),
            service(LoggerInterface::class)->nullOnInvalid(),
        ])
        // The default shipper; an application may alias ProfileShipperInterface to its own.
        ->alias(Profiling\Shipping\ProfileShipperInterface::class, Profiling\Shipping\PyroscopeShipper::class)

        ->set(Profiling\Request\EventListener\RequestProfilingSubscriber::class)
        ->autoconfigure()
        ->args([
            service(Profiling\Shipping\ProfileShipperInterface::class),
            service(Profiling\Encoding\PprofEncoder::class),
            tagged_iterator('instrumentation.profiling.label_provider'),
            service(MainSpanContextInterface::class)->nullOnInvalid(),
            service(LoggerInterface::class)->nullOnInvalid(),
            param('profiling.active'),
            param('profiling.frequency'),
            param('profiling.request_rate'),
            param('profiling.clock'),
            param('profiling.on_demand.token'),
            param('profiling.on_demand.header'),
            param('profiling.span_attribute'),
            param('profiling.max_depth'),
            service(QueryTally::class)->nullOnInvalid(),
            param('profiling.on_demand.statements'),
        ])
    ;
};
