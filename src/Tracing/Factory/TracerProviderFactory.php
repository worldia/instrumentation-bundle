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
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\ExporterFactory;
use OpenTelemetry\SDK\Trace\IdGeneratorInterface;
use OpenTelemetry\SDK\Trace\RandomIdGenerator;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\ResourceAttributes;

final class TracerProviderFactory
{
    use LogsMessagesTrait;

    private string $serviceName;
    private ExporterFactory $exporterFactory;
    private SamplerFactory $samplerFactory;
    private SpanProcessorFactory $spanProcessorFactory;
    private IdGeneratorInterface $idGenerator;

    public function __construct(
        private ResourceInfo $resourceInfo,
        ?ExporterFactory $exporterFactory = null,
        ?SamplerFactory $samplerFactory = null,
        ?SpanProcessorFactory $spanProcessorFactory = null,
        ?IdGeneratorInterface $idGenerator = null
    ) {
        $this->serviceName = $resourceInfo->getAttributes()->get(ResourceAttributes::SERVICE_NAME);
        $this->exporterFactory = $exporterFactory ?: new ExporterFactory($this->serviceName);
        $this->samplerFactory = $samplerFactory ?: new SamplerFactory();
        $this->spanProcessorFactory = $spanProcessorFactory ?: new SpanProcessorFactory();
        $this->idGenerator = $idGenerator ?: new RandomIdGenerator();
    }

    public function createFromDsn(string $dsn): API\TracerProviderInterface
    {
        $dsn = DsnParser::parseUrl($dsn);
        $processor = $dsn->getParameter('processor', SpanProcessorFactory::BATCH);
        $sampler = $dsn->getParameter('sampler', SamplerFactory::ALWAYS_ON);
        $ratio = $dsn->getParameter('ratio', .5);

        $url = $dsn
            ->withParameter('serviceName', $this->serviceName)
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

        return new TracerProvider($spanProcessor, $sampler, $this->resourceInfo, null, $this->idGenerator);
    }
}
