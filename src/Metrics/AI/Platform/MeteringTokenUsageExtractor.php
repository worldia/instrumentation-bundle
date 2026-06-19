<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Metrics\AI\Platform;

use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\SemConv\TraceAttributes;
use OpenTelemetry\SemConv\TraceAttributeValues;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;

/**
 * Records the OTel gen_ai `gen_ai.client.token.usage` histogram, split by
 * `gen_ai.token.type` (input/output), while delegating to the platform's own
 * token usage extractor so the result chain is untouched.
 */
final class MeteringTokenUsageExtractor implements TokenUsageExtractorInterface
{
    public function __construct(
        private readonly TokenUsageExtractorInterface $inner,
        private readonly MeterProviderInterface $meterProvider,
        private readonly string $system,
        private readonly string $model,
    ) {
    }

    public function extract(RawResultInterface $rawResult, array $options = []): TokenUsageInterface|null
    {
        $tokenUsage = $this->inner->extract($rawResult, $options);

        if (null === $tokenUsage) {
            return null;
        }

        $histogram = $this->meterProvider
            ->getMeter('instrumentation')
            ->createHistogram('gen_ai.client.token.usage', '{token}', 'Number of tokens used by the model');

        $attributes = [
            TraceAttributes::GEN_AI_OPERATION_NAME => TraceAttributeValues::GEN_AI_OPERATION_NAME_CHAT,
            TraceAttributes::GEN_AI_SYSTEM => $this->system,
            TraceAttributes::GEN_AI_REQUEST_MODEL => $this->model,
        ];

        if (null !== $tokenUsage->getPromptTokens()) {
            $histogram->record($tokenUsage->getPromptTokens(), $attributes + [
                TraceAttributes::GEN_AI_TOKEN_TYPE => TraceAttributeValues::GEN_AI_TOKEN_TYPE_INPUT,
            ]);
        }
        if (null !== $tokenUsage->getCompletionTokens()) {
            $histogram->record($tokenUsage->getCompletionTokens(), $attributes + [
                TraceAttributes::GEN_AI_TOKEN_TYPE => TraceAttributeValues::GEN_AI_TOKEN_TYPE_OUTPUT,
            ]);
        }

        return $tokenUsage;
    }
}
