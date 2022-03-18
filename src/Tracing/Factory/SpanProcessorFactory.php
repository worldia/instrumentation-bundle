<?php

declare(strict_types=1);

/*
 * This file is part of the platform/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Factory;

use InvalidArgumentException;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\SpanProcessor\NoopSpanProcessor;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\SpanProcessorInterface;

class SpanProcessorFactory
{
    public const BATCH = 'batch';
    public const SIMPLE = 'simple';
    public const NOOP = 'noop';
    public const NONE = 'none';

    public function create(string $type, SpanExporterInterface $exporter = null): SpanProcessorInterface
    {
        return match ($type) {
            self::BATCH => new BatchSpanProcessor($exporter),
            self::SIMPLE => new SimpleSpanProcessor($exporter),
            self::NOOP => NoopSpanProcessor::getInstance(),
            self::NONE => NoopSpanProcessor::getInstance(),
            default => throw new InvalidArgumentException('Unknown processor: '.$type)
        };
    }
}
