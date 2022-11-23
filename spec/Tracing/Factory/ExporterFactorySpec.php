<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace spec\Instrumentation\Tracing\Factory;

use Instrumentation\Tracing\Factory\ExporterFactory;
use OpenTelemetry\Contrib\Jaeger\Exporter as JaegerExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Common\Environment\Variables;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SemConv\ResourceAttributes;
use PhpSpec\ObjectBehavior;

class ExporterFactorySpec extends ObjectBehavior
{
    public function let()
    {
        $info = ResourceInfo::create(Attributes::create([ResourceAttributes::SERVICE_NAME => 'some-service']));
        $this->beConstructedWith($info);
    }

    public function it_is_initializable(): void
    {
        $this->beAnInstanceOf(ExporterFactory::class);
    }

    public function it_creates_exporter_from_string(): void
    {
        $this->createFromDsn('jaeger+http://jaeger:9411/')->shouldReturnAnInstanceOf(JaegerExporter::class);
    }

    public function it_creates_no_exporter_without_dsn_and_no_env_var(): void
    {
        $this->create()->shouldReturn(null);
    }

    public function it_creates_otlp_exporter_without_dsn(): void
    {
        $_ENV[Variables::OTEL_EXPORTER_OTLP_ENDPOINT] = 'http://otel-collector:4318';

        $this->create()->shouldReturnAnInstanceOf(\OpenTelemetry\Contrib\OtlpHttp\Exporter::class);

        unset($_ENV[Variables::OTEL_EXPORTER_OTLP_ENDPOINT]);
    }

    public function it_throws_exception_when_instantiating_otlp_exporter_through_dsn(): void
    {
        $this
            ->shouldThrow(new \InvalidArgumentException('OTLP exporters can not be instantiated through a DSN, provide OTEL_* env vars instead.'))
            ->during('create', ['otlp+http://otel-collector:4318']);
    }
}
