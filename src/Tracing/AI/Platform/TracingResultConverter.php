<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\AI\Platform;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

final class TracingResultConverter implements ResultConverterInterface
{
    public function __construct(
        private readonly ResultConverterInterface $inner,
        private readonly SpanInterface $span,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $this->inner->supports($model);
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        try {
            $converted = $this->inner->convert($result, $options);
        } catch (\Throwable $e) {
            $this->span->recordException($e);
            $this->span->setStatus(StatusCode::STATUS_ERROR);
            $this->span->end();
            throw $e;
        }

        $this->span->setStatus(StatusCode::STATUS_OK);

        return $converted;
        // On success, span is ended by TracingTokenUsageExtractor after token extraction.
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return new TracingTokenUsageExtractor($this->inner->getTokenUsageExtractor(), $this->span);
    }
}
