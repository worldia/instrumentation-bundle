<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Resources;

use Instrumentation\Tracing\Exporter\ResetSpanExporter;
use Instrumentation\Tracing\Factory\ExporterFactory;
use Instrumentation\Tracing\Factory\SamplerFactory;
use Instrumentation\Tracing\Factory\SpanProcessorFactory;
use Instrumentation\Tracing\Instrumentation\LogHandler\TracingHandler;
use Instrumentation\Tracing\Instrumentation\MainSpanContext;
use Instrumentation\Tracing\Instrumentation\MainSpanContextInterface;
use Instrumentation\Tracing\Propagation\ForcableIdGenerator;
use Instrumentation\Tracing\Propagation\IncomingTraceHeaderResolverInterface;
use Instrumentation\Tracing\Propagation\RegexIncomingTraceHeaderResolver;
use Instrumentation\Tracing\Sampling\TogglableSampler;
use Instrumentation\Tracing\Serializer\Normalizer\ErrorNormalizer;
use Instrumentation\Tracing\TraceUrlGeneratorInterface;
use Instrumentation\Tracing\Twig\Extension\TracingExtension;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\IdGeneratorInterface;
use OpenTelemetry\SDK\Trace\RandomIdGenerator;
use OpenTelemetry\SDK\Trace\SamplerInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\SpanProcessorInterface;
use OpenTelemetry\SDK\Trace\TracerProvider;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

use Symfony\Component\Serializer\Serializer;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set(ExporterFactory::class)
        ->args([
            service(ResourceInfo::class),
        ])

        ->set(SamplerFactory::class)
        ->set(SpanProcessorFactory::class)
        ->args([
            service(SpanExporterInterface::class),
        ])

        ->set(IdGeneratorInterface::class, RandomIdGenerator::class)

        ->set(ForcableIdGenerator::class)
        ->decorate(IdGeneratorInterface::class)
        ->args([
            service('.inner'),
        ])

        ->set(SamplerInterface::class)
        ->factory([service(SamplerFactory::class), 'createFromDsn'])
        ->args([param('tracer.dsn')])

        ->set(TogglableSampler::class)
        ->decorate(SamplerInterface::class)
        ->args([
            service('.inner'),
        ])

        ->set(SpanExporterInterface::class)
        ->factory([service(ExporterFactory::class), 'createFromDsn'])
        ->args([
            param('tracer.dsn'),
        ])

        ->set(ResetSpanExporter::class)
        ->args([
            service(SpanExporterInterface::class),
        ])

        ->set(SpanProcessorInterface::class)
        ->factory([service(SpanProcessorFactory::class), 'createFromDsn'])
        ->args([
            param('tracer.dsn'),
        ])

        ->set(TracerProviderInterface::class, TracerProvider::class)
        ->args([
            [service(SpanProcessorInterface::class)],
            service(SamplerInterface::class),
            service(ResourceInfo::class),
            null,
            service(IdGeneratorInterface::class),
        ])
        ->public()

        ->set(MainSpanContextInterface::class, MainSpanContext::class)

        ->set(TracingHandler::class)
        ->args([
            service(TracerProviderInterface::class),
            service(MainSpanContextInterface::class),
            param('tracing.logs.level'),
            param('tracing.logs.channels'),
        ])

        ->set(IncomingTraceHeaderResolverInterface::class, RegexIncomingTraceHeaderResolver::class)
        ->args([
            param('tracing.request.incoming_header.name'),
            param('tracing.request.incoming_header.regex'),
        ]);

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
