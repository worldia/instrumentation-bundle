<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

use Instrumentation\Semantics;
use Instrumentation\Tracing;
use Instrumentation\Tracing\Bridge\MainSpanContextInterface;
use Instrumentation\Tracing\Bridge\Sampling;
use Instrumentation\Tracing\Instrumentation;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set(Tracing\Request\EventListener\RequestEventSubscriber::class)
        ->args([
            service(TracerProviderInterface::class),
            service(MainSpanContextInterface::class),
            service(Semantics\OperationName\ServerRequestOperationNameResolverInterface::class),
            service(Semantics\Attribute\ServerRequestAttributeProviderInterface::class),
            service(Semantics\Attribute\ServerResponseAttributeProviderInterface::class),
        ])
        ->autoconfigure()

        ->set(Sampling\Voter\RequestVoterInterface::class, Sampling\Voter\RequestVoter::class)
        ->args([
            param('tracing.request.blacklist'),
            param('tracing.request.methods'),
        ])
        ->autoconfigure()

        ->set(Sampling\EventListener\RequestEventSubscriber::class)
        ->args([
            service(Sampling\TogglableSampler::class),
            service(Sampling\Voter\RequestVoterInterface::class),
        ])
        ->autoconfigure()

        ->set(Tracing\Request\EventListener\AddUserEventSubscriber::class)
        ->args([
            service(TokenStorageInterface::class)->nullOnInvalid(),
        ])
        ->autoconfigure()
    ;
};
