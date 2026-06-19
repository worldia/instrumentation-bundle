<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\AI\Platform;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\SemConv\TraceAttributes;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;

final class TracingTokenUsageExtractor implements TokenUsageExtractorInterface
{
    public function __construct(
        private readonly TokenUsageExtractorInterface|null $inner,
        private readonly SpanInterface $span,
    ) {
    }

    public function extract(RawResultInterface $rawResult, array $options = []): TokenUsageInterface|null
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
                $this->span->setAttribute(TraceAttributes::GEN_AI_USAGE_INPUT_TOKENS, $tokenUsage->getPromptTokens());
            }
            if (null !== $tokenUsage->getCompletionTokens()) {
                $this->span->setAttribute(TraceAttributes::GEN_AI_USAGE_OUTPUT_TOKENS, $tokenUsage->getCompletionTokens());
            }

            return $tokenUsage;
        } finally {
            $this->span->end();
        }
    }
}
