<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Resources;

use Instrumentation\Semantics;
use Instrumentation\Tracing\Instrumentation;
use Instrumentation\Tracing\Instrumentation\MainSpanContextInterface;
use Instrumentation\Tracing\Sampling;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set(Instrumentation\EventSubscriber\CommandEventSubscriber::class)
        ->args([
            service(TracerProviderInterface::class),
            service(MainSpanContextInterface::class),
            service(Semantics\OperationName\CommandOperationNameResolverInterface::class),
        ])
        ->autoconfigure()

        ->set(Sampling\Voter\CommandVoterInterface::class, Sampling\Voter\CommandVoter::class)
        ->args([
            param('tracing.command.blacklist'),
        ])
        ->autoconfigure()

        ->set(Sampling\EventSubscriber\CommandEventSubscriber::class)
        ->args([
            service(Sampling\TogglableSampler::class),
            service(Sampling\Voter\CommandVoterInterface::class),
        ])
        ->autoconfigure()
    ;
};
