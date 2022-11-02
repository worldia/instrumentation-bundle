<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Sampler;

use OpenTelemetry\Context\Context;
use OpenTelemetry\SDK\Common\Attribute\AttributesInterface;
use OpenTelemetry\SDK\Trace\SamplerInterface;
use OpenTelemetry\SDK\Trace\SamplingResult;
use Symfony\Contracts\Service\ResetInterface;

final class TogglableSampler implements SamplerInterface, ResetInterface
{
    /**
     * @var int&SamplingResult::*
     */
    private ?int $nextDecision = null;

    public function __construct(private SamplerInterface $decorated)
    {
    }

    /**
     * @param AttributesInterface<string,string> $attributes
     */
    public function shouldSample(Context $parentContext, string $traceId, string $spanName, int $spanKind, AttributesInterface $attributes, array $links = []): SamplingResult
    {
        $result = $this->decorated->shouldSample($parentContext, $traceId, $spanName, $spanKind, $attributes, $links);

        if (null === $this->nextDecision) {
            return $result;
        }

        $decision = $this->nextDecision;

        $this->reset();

        return new SamplingResult($decision, $result->getAttributes(), $result->getTraceState());
    }

    public function getDescription(): string
    {
        return $this->decorated->getDescription();
    }

    public function recordNext(): void
    {
        $this->nextDecision = SamplingResult::RECORD_ONLY;
    }

    public function recordAndSampleNext(): void
    {
        $this->nextDecision = SamplingResult::RECORD_AND_SAMPLE;
    }

    public function dropNext(): void
    {
        $this->nextDecision = SamplingResult::DROP;
    }

    public function reset(): void
    {
        $this->nextDecision = null;
    }
}
