<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Metrics\AI\Platform;

use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

final class MeteringResultConverter implements ResultConverterInterface
{
    private bool $durationRecorded = false;

    /**
     * @param int $start the hrtime(true) nanosecond timestamp captured when the platform was invoked
     */
    public function __construct(
        private readonly ResultConverterInterface $inner,
        private readonly AiMetricRecorder $recorder,
        private readonly string $model,
        private readonly int $start,
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
            $this->recordDuration($e);

            throw $e;
        }

        $this->recordDuration(null);

        return $converted;
    }

    public function getTokenUsageExtractor(): TokenUsageExtractorInterface|null
    {
        $inner = $this->inner->getTokenUsageExtractor();

        if (null === $inner) {
            return null;
        }

        return new MeteringTokenUsageExtractor($inner, $this->recorder, $this->model);
    }

    private function recordDuration(\Throwable|null $error): void
    {
        if ($this->durationRecorded) {
            return;
        }

        $this->durationRecorded = true;
        $this->recorder->recordDuration($this->model, (hrtime(true) - $this->start) / 1e9, $error);
    }
}
