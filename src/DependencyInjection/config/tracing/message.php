<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Resources;

use Instrumentation\Semantics;
use Instrumentation\Tracing;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\SDK\Trace\SpanProcessorInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set(Tracing\Propagation\EventSubscriber\MessengerEventSubscriber::class)
        ->autoconfigure()

        ->set(Tracing\Instrumentation\EventSubscriber\MessageEventSubscriber::class)
        ->args([
            service(TracerProviderInterface::class),
            service(Tracing\Instrumentation\MainSpanContextInterface::class),
            service(Semantics\OperationName\MessageOperationNameResolverInterface::class),
            service(Semantics\Attribute\MessageAttributeProviderInterface::class),
            service(SpanProcessorInterface::class),
        ])
        ->autoconfigure()

        ->set(Tracing\Sampling\Voter\MessageVoterInterface::class, Tracing\Sampling\Voter\MessageVoter::class)
        ->args([
            param('tracing.message.blacklist'),
        ])
        ->autoconfigure()
        ->set(Tracing\Sampling\EventSubscriber\MessageEventSubscriber::class)
        ->args([
            service(Tracing\Sampling\TogglableSampler::class),
            service(Tracing\Sampling\Voter\MessageVoterInterface::class),
        ])
        ->autoconfigure()
    ;
};
