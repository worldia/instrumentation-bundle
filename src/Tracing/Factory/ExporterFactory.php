<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Factory;

use Nyholm\Dsn\DsnParser;
use OpenTelemetry\SDK\Common\Environment\Resolver;
use OpenTelemetry\SDK\Common\Environment\Variables;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\ExporterFactory as OtelExporterFactory;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SemConv\ResourceAttributes;

class ExporterFactory
{
    private string $serviceName;
    private OtelExporterFactory $exporterFactory;

    public function __construct(ResourceInfo $info)
    {
        $this->serviceName = $info->getAttributes()->get(ResourceAttributes::SERVICE_NAME);
        $this->exporterFactory = new OtelExporterFactory($this->serviceName);
    }

    public function create(?string $dsn = null): ?SpanExporterInterface
    {
        if ($dsn) {
            return $this->createFromDsn($dsn);
        } elseif (!Resolver::hasVariable(Variables::OTEL_EXPORTER_OTLP_ENDPOINT)) {
            return null;
        }

        return $this->exporterFactory->fromEnvironment();
    }

    public function createFromDsn(string $dsn): SpanExporterInterface
    {
        $dsn = DsnParser::parseUrl($dsn);

        if (0 === strpos($dsn->getScheme(), 'otlp+')) {
            throw new \InvalidArgumentException('OTLP exporters can not be instantiated through a DSN, provide OTEL_* env vars instead.');
        }

        $url = $dsn
            ->withParameter('serviceName', $this->serviceName)
            ->withoutParameter('processor')
            ->withoutParameter('sampler')
            ->withoutParameter('ratio');

        return $this->exporterFactory->fromConnectionString((string) $url);
    }
}
