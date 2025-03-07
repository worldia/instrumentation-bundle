<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

use Instrumentation\Tracing\Bridge\Exporter\ResetSpanExporter;
use Instrumentation\Tracing\Bridge\MainSpanContext;
use Instrumentation\Tracing\Bridge\MainSpanContextInterface;
use Instrumentation\Tracing\Bridge\Profiler\DataCollector\TraceContextDataCollector;
use Instrumentation\Tracing\Bridge\Sampling\TogglableSampler;
use Instrumentation\Tracing\Bridge\Serializer\Normalizer\ErrorNormalizer;
use Instrumentation\Tracing\Bridge\TraceUrlGeneratorInterface;
use Instrumentation\Tracing\Bridge\Twig\Extension\TracingExtension;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\SDK\Common\Attribute\AttributesFactory;
use OpenTelemetry\SDK\Common\Attribute\AttributesFactoryInterface;
use OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeFactory;
use OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeFactoryInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\ExporterFactory;
use OpenTelemetry\SDK\Trace\IdGeneratorInterface;
use OpenTelemetry\SDK\Trace\RandomIdGenerator;
use OpenTelemetry\SDK\Trace\SamplerFactory;
use OpenTelemetry\SDK\Trace\SamplerInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\SpanLimits;
use OpenTelemetry\SDK\Trace\SpanLimitsBuilder;
use OpenTelemetry\SDK\Trace\SpanProcessorFactory;
use OpenTelemetry\SDK\Trace\SpanProcessorInterface;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\TracerProviderFactory;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Serializer\Serializer;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set(ExporterFactory::class)
        ->set(SamplerFactory::class)

        ->set(SpanProcessorFactory::class)
        ->args([
            service(SpanExporterInterface::class),
        ])

        ->set(IdGeneratorInterface::class, RandomIdGenerator::class)

        ->set(SamplerInterface::class)
        ->factory([service(SamplerFactory::class), 'create'])

        ->set(TogglableSampler::class)
        ->decorate(SamplerInterface::class)
        ->args([
            service('.inner'),
        ])

        ->set(SpanExporterInterface::class)
        ->factory([service(ExporterFactory::class), 'create'])

        ->set(ResetSpanExporter::class)
        ->args([
            service(SpanExporterInterface::class),
        ])

        ->set(SpanProcessorInterface::class)
        ->factory([service(SpanProcessorFactory::class), 'create'])
        ->args([service(SpanExporterInterface::class)])

        ->set(SpanLimitsBuilder::class)

        ->set(SpanLimits::class)
        ->factory([service(SpanLimitsBuilder::class), 'build'])

        ->set(AttributesFactoryInterface::class)
        ->class(AttributesFactory::class)

        ->set(InstrumentationScopeFactoryInterface::class)
        ->class(InstrumentationScopeFactory::class)
        ->args([
            service(AttributesFactoryInterface::class),
        ])

        ->set(TracerProviderFactory::class)

        ->set(TracerProviderInterface::class, TracerProvider::class)
        ->args([
            [service(SpanProcessorInterface::class)],
            service(SamplerInterface::class),
            service(ResourceInfo::class),
            service(SpanLimits::class),
            service(IdGeneratorInterface::class),
            service(InstrumentationScopeFactoryInterface::class),
        ])
        ->public()

        ->set(MainSpanContextInterface::class, MainSpanContext::class)
    ;

    if ('dev' === $container->env()) {
        $container->services()
            ->set(TraceContextDataCollector::class)
            ->args([
                service(TraceUrlGeneratorInterface::class)->nullOnInvalid(),
            ])
            ->tag('data_collector', [
                'id' => TraceContextDataCollector::class,
                // optional template (it has more priority than the value returned by getTemplate())
                // 'template' => 'data_collector/template.html.twig',
                // optional priority (positive or negative integer; default = 0)
                // 'priority' => 300,
            ]);
    }

    if (class_exists(Serializer::class)) {
        $container->services()
            ->set(ErrorNormalizer::class)
            ->decorate('serializer.normalizer.problem')
            ->args([
                service('.inner'),
                param('kernel.debug'),
                service(TraceUrlGeneratorInterface::class)->nullOnInvalid(),
            ]);
    }

    if (class_exists(TwigBundle::class)) {
        $container->services()
            ->set(TracingExtension::class)
            ->args([
                service(TraceUrlGeneratorInterface::class)->nullOnInvalid(),
            ])
            ->tag('twig.extension');
    }
};
