<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Factory;

use Nyholm\Dsn\DsnParser;
use OpenTelemetry\SDK\Common\Time\ClockFactory;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\SpanProcessor\NoopSpanProcessor;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\SpanProcessorFactory as OtelSpanProcessorFactory;
use OpenTelemetry\SDK\Trace\SpanProcessorInterface;

class SpanProcessorFactory
{
    public const BATCH = 'batch';
    public const SIMPLE = 'simple';
    public const NOOP = 'noop';
    public const NONE = 'none';

    public function __construct(private ?SpanExporterInterface $exporter = null)
    {
    }

    public function create(?string $dsn): SpanProcessorInterface
    {
        if (!$this->exporter) {
            return new NoopSpanProcessor();
        }

        if ($dsn) {
            return $this->createFromDsn($dsn);
        }

        return (new OtelSpanProcessorFactory())->fromEnvironment($this->exporter);
    }

    private function createFromDsn(string $dsn): SpanProcessorInterface
    {
        $dsn = DsnParser::parseUrl($dsn);
        $type = $dsn->getParameter('processor', self::BATCH);

        return $this->doCreate($type, $this->exporter);
    }

    private function doCreate(string $type, SpanExporterInterface $exporter): SpanProcessorInterface
    {
        return match ($type) {
            self::BATCH => new BatchSpanProcessor($exporter, ClockFactory::getDefault()),
            self::SIMPLE => new SimpleSpanProcessor($exporter),
            self::NOOP => NoopSpanProcessor::getInstance(),
            self::NONE => NoopSpanProcessor::getInstance(),
            default => throw new \InvalidArgumentException('Unknown processor: '.$type)
        };
    }
}
