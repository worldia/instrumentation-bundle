<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Resources;

use Instrumentation\Tracing\Factory\SamplerFactory;
use Instrumentation\Tracing\Factory\SpanProcessorFactory;
use Instrumentation\Tracing\Factory\TracerProviderFactory;
use Instrumentation\Tracing\Instrumentation\EventSubscriber\ToggleTracerSubscriber;
use Instrumentation\Tracing\Instrumentation\TogglableTracerProvider;
use Instrumentation\Tracing\Serializer\Normalizer\ErrorNormalizer;
use Instrumentation\Tracing\TraceUrlGeneratorInterface;
use Instrumentation\Tracing\Twig\Extension\TracingExtension;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\SDK\Trace\ExporterFactory;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use Symfony\Component\Serializer\Serializer;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set(ExporterFactory::class)
        ->args([
            'traced-app', // will be overidden by serviceName query param in DSN
        ])

        ->set(SamplerFactory::class)
        ->set(SpanProcessorFactory::class)

        ->set(TracerProviderFactory::class)
        ->args([
            'traced-app', // will be overidden by serviceName query param in DSN
            service(ExporterFactory::class),
            service(SamplerFactory::class),
            service(SpanProcessorFactory::class),
        ])

        ->set(TracerProviderInterface::class)
        ->factory([service(TracerProviderFactory::class), 'createFromDsn'])
        ->args([param('tracer.dsn')])
        ->public()

        ->set(TogglableTracerProvider::class)
        ->decorate(TracerProviderInterface::class)
        ->args([
            service('.inner'),
        ])

        ->set(ToggleTracerSubscriber::class)
        ->args([
            service(TogglableTracerProvider::class),
            param('tracing.request.blacklist'),
            param('tracing.command.blacklist'),
            param('tracing.message.blacklist'),
        ])
        ->autoconfigure()
    ;

    if (class_exists(Serializer::class)) {
        $container->services()
            ->set(ErrorNormalizer::class)
            ->decorate('serializer.normalizer.problem')
            ->args([
                service('.inner'),
                param('kernel.debug'),
                service(TraceUrlGeneratorInterface::class)->nullOnInvalid(),
            ])
            ->tag('serializer.normalizer')
            ->autoconfigure();
    }

    if (class_exists(TwigBundle::class)) {
        $container->services()
            ->set(TracingExtension::class)
            ->args([
                service(TraceUrlGeneratorInterface::class)->nullOnInvalid(),
            ])
            ->tag('twig.extension');

        try {
            $container->extension('twig', [
                'paths' => [
                    __DIR__.'/../../../Tracing/Twig/Templates' => 'Twig',
                ],
            ]);
        } catch (InvalidArgumentException) {
            // Do not throw error if there is no extension able to load the configuration for "twig"
        }
    }
};
