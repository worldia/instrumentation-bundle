<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Factory;

use Nyholm\Dsn\DsnParser;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOffSampler;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\Sampler\TraceIdRatioBasedSampler;
use OpenTelemetry\SDK\Trace\SamplerInterface;

class SamplerFactory
{
    public const TRACEIDRATIO = 'traceidratio';
    public const PARENTBASED_TRACEIDRATIO = 'parentbased_traceidratio';
    public const ALWAYS_ON = 'always_on';
    public const ALWAYS_OFF = 'always_off';
    public const PARENTBASED_ALWAYS_ON = 'parentbased_always_on';
    public const PARENTBASED_ALWAYS_OFF = 'parentbased_always_off';

    public function create(string $type, ?float $ratio = null): SamplerInterface
    {
        if (!\is_float($ratio) && \in_array($type, [self::TRACEIDRATIO, self::PARENTBASED_TRACEIDRATIO])) {
            throw new \RuntimeException('You must provide a ratio to use trace id ratio sampling.');
        }

        return match ($type) {
            self::TRACEIDRATIO => new TraceIdRatioBasedSampler((float) $ratio),
            self::ALWAYS_ON => new AlwaysOnSampler(),
            self::ALWAYS_OFF => new AlwaysOffSampler(),
            self::PARENTBASED_TRACEIDRATIO => new ParentBased(new TraceIdRatioBasedSampler((float) $ratio)),
            self::PARENTBASED_ALWAYS_ON => new ParentBased(new AlwaysOnSampler()),
            self::PARENTBASED_ALWAYS_OFF => new ParentBased(new AlwaysOffSampler()),
            default => throw new \InvalidArgumentException('Unknown sampler: '.$type)
        };
    }

    public function createFromDsn(string $dsn): SamplerInterface
    {
        $dsn = DsnParser::parseUrl($dsn);
        $type = $dsn->getParameter('sampler', self::PARENTBASED_ALWAYS_ON);
        $ratio = (float) $dsn->getParameter('ratio', .5);

        return $this->create($type, $ratio);
    }
}
