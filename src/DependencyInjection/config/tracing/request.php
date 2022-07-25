<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Resources;

use Instrumentation\Semantics\Attribute\ServerRequestAttributeProviderInterface;
use Instrumentation\Semantics\Attribute\ServerResponseAttributeProviderInterface;
use Instrumentation\Tracing;
use Instrumentation\Tracing\Instrumentation\MainSpanContext;
use Instrumentation\Tracing\Propagation\ForcableIdGenerator;
use Instrumentation\Tracing\Propagation\IncomingTraceHeaderResolverInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

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
            service(ServerRequestAttributeProviderInterface::class),
            service(ServerResponseAttributeProviderInterface::class),
            service(MainSpanContext::class),
        ])
        ->autoconfigure()

        ->set(Tracing\Instrumentation\EventSubscriber\AddUserEventSubscriber::class)
        ->args([
            service(MainSpanContext::class),
            service(TokenStorageInterface::class)->nullOnInvalid(),
        ])
        ->autoconfigure();
};
