<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Metrics\AI\Platform;

use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;

/**
 * Records the OTel gen_ai `gen_ai.client.token.usage` histogram while delegating
 * to the platform's own token usage extractor so the result chain is untouched.
 */
final class MeteringTokenUsageExtractor implements TokenUsageExtractorInterface
{
    public function __construct(
        private readonly TokenUsageExtractorInterface $inner,
        private readonly AiMetricRecorder $recorder,
        private readonly string $model,
    ) {
    }

    public function extract(RawResultInterface $rawResult, array $options = []): TokenUsageInterface|null
    {
        $tokenUsage = $this->inner->extract($rawResult, $options);

        if (null === $tokenUsage) {
            return null;
        }

        $this->recorder->recordTokenUsage($this->model, $tokenUsage);

        return $tokenUsage;
    }
}
