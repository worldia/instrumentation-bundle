<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Resources;

use Instrumentation\Semantics\Attribute\MessageAttributeProviderInterface;
use Instrumentation\Semantics\OperationName\MessageOperationNameResolverInterface;
use Instrumentation\Tracing;
use Instrumentation\Tracing\Instrumentation\MainSpanContextInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\SDK\Trace\SpanProcessorInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set(Tracing\Propagation\EventSubscriber\MessengerEventSubscriber::class)
        ->autoconfigure()

        ->set(Tracing\Instrumentation\EventSubscriber\MessageEventSubscriber::class)
        ->args([
            service(TracerProviderInterface::class),
            service(MainSpanContextInterface::class),
            service(MessageOperationNameResolverInterface::class),
            service(MessageAttributeProviderInterface::class),
            service(SpanProcessorInterface::class),
        ])
        ->autoconfigure();
};
