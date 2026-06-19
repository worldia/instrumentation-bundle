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
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;

/**
 * Records the OTel gen_ai client metrics (semconv) for a platform call:
 * the `gen_ai.client.token.usage` and `gen_ai.client.operation.duration`
 * histograms, sharing the common gen_ai.* attribute set.
 */
final class AiMetricRecorder
{
    public function __construct(
        private readonly MeterProviderInterface $meterProvider,
        private readonly string $system,
    ) {
    }

    public function recordTokenUsage(string $model, TokenUsageInterface $tokenUsage): void
    {
        $histogram = $this->meterProvider
            ->getMeter('instrumentation')
            ->createHistogram('gen_ai.client.token.usage', '{token}', 'Number of tokens used by the model');

        if (null !== $tokenUsage->getPromptTokens()) {
            $histogram->record($tokenUsage->getPromptTokens(), $this->attributes($model) + [
                TraceAttributes::GEN_AI_TOKEN_TYPE => TraceAttributeValues::GEN_AI_TOKEN_TYPE_INPUT,
            ]);
        }
        if (null !== $tokenUsage->getCompletionTokens()) {
            $histogram->record($tokenUsage->getCompletionTokens(), $this->attributes($model) + [
                TraceAttributes::GEN_AI_TOKEN_TYPE => TraceAttributeValues::GEN_AI_TOKEN_TYPE_OUTPUT,
            ]);
        }
    }

    public function recordDuration(string $model, float $seconds, \Throwable|null $error = null): void
    {
        $attributes = $this->attributes($model);

        if (null !== $error) {
            $attributes[TraceAttributes::ERROR_TYPE] = $error::class;
        }

        $this->meterProvider
            ->getMeter('instrumentation')
            ->createHistogram('gen_ai.client.operation.duration', 's', 'GenAI operation duration')
            ->record($seconds, $attributes);
    }

    /**
     * @return array<non-empty-string,string>
     */
    private function attributes(string $model): array
    {
        return [
            TraceAttributes::GEN_AI_OPERATION_NAME => TraceAttributeValues::GEN_AI_OPERATION_NAME_CHAT,
            TraceAttributes::GEN_AI_SYSTEM => $this->system,
            TraceAttributes::GEN_AI_REQUEST_MODEL => $model,
        ];
    }
}
