<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Resources;

use Instrumentation\Semantics;
use Instrumentation\Tracing;
use Instrumentation\Tracing\Instrumentation;
use Instrumentation\Tracing\Propagation;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set(Tracing\Propagation\EventSubscriber\RequestEventSubscriber::class)
        ->args([
            service(Propagation\ForcableIdGenerator::class),
            service(Propagation\IncomingTraceHeaderResolverInterface::class)->nullOnInvalid(),
        ])
        ->autoconfigure()

        ->set(Tracing\Instrumentation\EventSubscriber\RequestEventSubscriber::class)
        ->args([
            service(TracerProviderInterface::class),
            service(Instrumentation\MainSpanContextInterface::class),
            service(Semantics\OperationName\ServerRequestOperationNameResolverInterface::class),
            service(Semantics\Attribute\ServerRequestAttributeProviderInterface::class),
            service(Semantics\Attribute\ServerResponseAttributeProviderInterface::class),
        ])
        ->autoconfigure()

        ->set(Tracing\Sampling\Voter\RequestVoterInterface::class, Tracing\Sampling\Voter\RequestVoter::class)
        ->args([
            param('tracing.request.blacklist'),
            param('tracing.request.methods'),
        ])
        ->autoconfigure()
        ->set(Tracing\Sampling\EventSubscriber\RequestEventSubscriber::class)
        ->args([
            service(Tracing\Sampling\TogglableSampler::class),
            service(Tracing\Sampling\Voter\RequestVoterInterface::class),
        ])
        ->autoconfigure()

        ->set(Tracing\Instrumentation\EventSubscriber\AddUserEventSubscriber::class)
        ->args([
            service(Instrumentation\MainSpanContextInterface::class),
            service(TokenStorageInterface::class)->nullOnInvalid(),
        ])
        ->autoconfigure();
};
