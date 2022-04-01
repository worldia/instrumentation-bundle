<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Resources;

use Instrumentation\Semantics\Attribute\RequestAttributeProviderInterface;
use Instrumentation\Semantics\Attribute\ResponseAttributeProviderInterface;
use Instrumentation\Tracing;
use Instrumentation\Tracing\Instrumentation\MainSpanContext;
use Instrumentation\Tracing\Propagation\ForcableIdGenerator;
use Instrumentation\Tracing\Propagation\IncomingTraceHeaderResolverInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use Symfony\Component\Routing\RouterInterface;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set(Tracing\Propagation\EventSubscriber\RequestEventSubscriber::class)
        ->args([
            service(ForcableIdGenerator::class),
            service(IncomingTraceHeaderResolverInterface::class)->nullOnInvalid(),
        ])
        ->autoconfigure()

        ->set(Tracing\Instrumentation\EventSubscriber\RequestEventSubscriber::class)
        ->args([
            service(TracerProviderInterface::class),
            service(RouterInterface::class),
            service(RequestAttributeProviderInterface::class),
            service(ResponseAttributeProviderInterface::class),
            service(MainSpanContext::class),
        ])
        ->autoconfigure();
};
