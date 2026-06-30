<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\AI\Platform;

use OpenTelemetry\API\Trace\SpanInterface;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;

final class TracingTokenUsageExtractor implements TokenUsageExtractorInterface
{
    public function __construct(
        private readonly ?TokenUsageExtractorInterface $inner,
        private readonly SpanInterface $span,
    ) {
    }

    public function extract(RawResultInterface $rawResult, array $options = []): ?TokenUsageInterface
    {
        try {
            if (null === $this->inner) {
                return null;
            }

            $tokenUsage = $this->inner->extract($rawResult, $options);

            if (null === $tokenUsage) {
                return null;
            }

            if (null !== $tokenUsage->getPromptTokens()) {
                $this->span->setAttribute('gen_ai.usage.input_tokens', $tokenUsage->getPromptTokens());
            }
            if (null !== $tokenUsage->getCompletionTokens()) {
                $this->span->setAttribute('gen_ai.usage.output_tokens', $tokenUsage->getCompletionTokens());
            }

            return $tokenUsage;
        } finally {
            $this->span->end();
        }
    }
}
