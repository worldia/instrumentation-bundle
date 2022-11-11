<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Factory;

use Instrumentation\Tracing\Exporter\NullExporter;
use Nyholm\Dsn\DsnParser;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\ExporterFactory as BaseExporterFactory;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SemConv\ResourceAttributes;

class ExporterFactory
{
    private string $serviceName;
    private BaseExporterFactory $exporterFactory;

    public function __construct(ResourceInfo $info)
    {
        $this->serviceName = $info->getAttributes()->get(ResourceAttributes::SERVICE_NAME);
        $this->exporterFactory = new BaseExporterFactory($this->serviceName);
    }

    public function createFromDsn(string $dsn): SpanExporterInterface
    {
        $dsn = DsnParser::parseUrl($dsn);

        if ('null' === $dsn->getScheme()) {
            return new NullExporter();
        }

        $url = $dsn
            ->withParameter('serviceName', $this->serviceName)
            ->withoutParameter('processor')
            ->withoutParameter('sampler')
            ->withoutParameter('ratio');

        return $this->exporterFactory->fromConnectionString((string) $url);
    }
}
