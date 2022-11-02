<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace spec\Instrumentation\Tracing\Factory;

use Instrumentation\Tracing\Factory\SpanProcessorFactory;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\SpanProcessor\NoopSpanProcessor;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use PhpSpec\ObjectBehavior;

class SpanProcessorFactorySpec extends ObjectBehavior
{
    public function let(SpanExporterInterface $exporter)
    {
        $this->beConstructedWith($exporter);
    }

    public function it_is_initializable(): void
    {
        $this->beAnInstanceOf(SpanProcessorFactory::class);
    }

    public function it_creates_span_processors(SpanExporterInterface $exporter): void
    {
        $this->create('batch', $exporter)->shouldReturnAnInstanceOf(BatchSpanProcessor::class);
        $this->create('simple', $exporter)->shouldReturnAnInstanceOf(SimpleSpanProcessor::class);
        $this->create('noop', $exporter)->shouldReturnAnInstanceOf(NoopSpanProcessor::class);
        $this->create('none', $exporter)->shouldReturnAnInstanceOf(NoopSpanProcessor::class);
    }

    public function it_throws_an_exception_for_unknown_span_processor(SpanExporterInterface $exporter): void
    {
        $this->shouldThrow(\InvalidArgumentException::class)->during('create', ['some_processor', $exporter]);
    }
}
