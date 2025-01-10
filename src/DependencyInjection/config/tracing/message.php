<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

use Instrumentation\Semantics;
use Instrumentation\Tracing\Bridge\MainSpanContextInterface;
use Instrumentation\Tracing\Bridge\Sampling;
use Instrumentation\Tracing\Messenger\EventListener\MessageEventSubscriber;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container) {
    $container->services()

        ->set(MessageEventSubscriber::class)
        ->args([
            service(TracerProviderInterface::class),
            service(MainSpanContextInterface::class),
            service(Semantics\OperationName\MessageOperationNameResolverInterface::class),
            service(Semantics\Attribute\MessageAttributeProviderInterface::class),
            param('tracing.message.flush_spans_after_handling'),
        ])
        ->autoconfigure()

        ->set(Sampling\Voter\MessageVoterInterface::class, Sampling\Voter\MessageVoter::class)
        ->args([
            param('tracing.message.blacklist'),
        ])
        ->autoconfigure()
        ->set(Sampling\EventListener\MessageEventSubscriber::class)
        ->args([
            service(Sampling\TogglableSampler::class),
            service(Sampling\Voter\MessageVoterInterface::class),
        ])
        ->autoconfigure()
    ;
};
