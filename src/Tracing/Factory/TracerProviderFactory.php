<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Factory;

use Nyholm\Dsn\DsnParser;
use OpenTelemetry\API\Trace as API;
use OpenTelemetry\SDK\Behavior\LogsMessagesTrait;
use OpenTelemetry\SDK\Trace\ExporterFactory;
use OpenTelemetry\SDK\Trace\TracerProvider;

final class TracerProviderFactory
{
    use LogsMessagesTrait;

    private ExporterFactory $exporterFactory;
    private SamplerFactory $samplerFactory;
    private SpanProcessorFactory $spanProcessorFactory;

    public function __construct(
        string $name,
        ?ExporterFactory $exporterFactory = null,
        ?SamplerFactory $samplerFactory = null,
        ?SpanProcessorFactory $spanProcessorFactory = null
    ) {
        $this->exporterFactory = $exporterFactory ?: new ExporterFactory($name);
        $this->samplerFactory = $samplerFactory ?: new SamplerFactory();
        $this->spanProcessorFactory = $spanProcessorFactory ?: new SpanProcessorFactory();
    }

    public function createFromDsn(string $dsn): API\TracerProviderInterface
    {
        $dsn = DsnParser::parseUrl($dsn);
        $processor = $dsn->getParameter('processor', 'batch');
        $sampler = $dsn->getParameter('sampler', 'always_on');
        $ratio = $dsn->getParameter('ratio', .5);

        $url = $dsn
            ->withoutParameter('processor')
            ->withoutParameter('sampler')
            ->withoutParameter('ratio');

        try {
            $exporter = $this->exporterFactory->fromConnectionString((string) $url);
        } catch (\Throwable $t) {
            $this->logWarning('Unable to create exporter', ['error' => $t]);
            $exporter = null;
        }

        try {
            $sampler = $this->samplerFactory->create($sampler, $ratio);
        } catch (\Throwable $t) {
            $this->logWarning('Unable to create sampler', ['error' => $t]);
            $sampler = null;
        }

        try {
            $spanProcessor = $this->spanProcessorFactory->create($processor, $exporter);
        } catch (\Throwable $t) {
            $this->logWarning('Unable to create span processor', ['error' => $t]);
            $spanProcessor = null;
        }

        return new TracerProvider($spanProcessor, $sampler);
    }
}
