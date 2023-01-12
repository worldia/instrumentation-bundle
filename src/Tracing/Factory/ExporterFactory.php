<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Factory;

use Nyholm\Dsn\DsnParser;
use OpenTelemetry\SDK\Trace\ExporterFactory as BaseExporterFactory;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;

class ExporterFactory
{
    private BaseExporterFactory $exporterFactory;

    public function __construct()
    {
        $this->exporterFactory = new BaseExporterFactory();
    }

    public function create(?string $dsn = null): ?SpanExporterInterface
    {
        if ($dsn) {
            $dsn = DsnParser::parseUrl($dsn);

            $dsn = $dsn
                ->withoutParameter('processor')
                ->withoutParameter('sampler')
                ->withoutParameter('ratio');

            try {
                [$exporter, $protocol] = explode('+', (string) $dsn->getScheme());
                $dsn = $dsn->withScheme($protocol);
            } catch (\Throwable) {
                throw new \InvalidArgumentException('Malformed DSN.');
            }

            putenv('OTEL_TRACES_EXPORTER='.$exporter);

            if ('zipkin' === $exporter) {
                putenv('OTEL_EXPORTER_ZIPKIN_ENDPOINT='.(string) $dsn);
            } elseif ('otlp' === $exporter) {
                putenv('OTEL_EXPORTER_OTLP_TRACES_PROTOCOL='.$protocol);
                putenv('OTEL_EXPORTER_OTLP_TRACES_ENDPOINT='.(string) $dsn);
            }
        }

        return $this->exporterFactory->create();
    }
}
