<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Metrics\AI\Platform;

use OpenTelemetry\API\Metrics\MeterProviderInterface;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

final class MeteringResultConverter implements ResultConverterInterface
{
    public function __construct(
        private readonly ResultConverterInterface $inner,
        private readonly MeterProviderInterface $meterProvider,
        private readonly string $system,
        private readonly string $model,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $this->inner->supports($model);
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        return $this->inner->convert($result, $options);
    }

    public function getTokenUsageExtractor(): TokenUsageExtractorInterface|null
    {
        $inner = $this->inner->getTokenUsageExtractor();

        if (null === $inner) {
            return null;
        }

        return new MeteringTokenUsageExtractor($inner, $this->meterProvider, $this->system, $this->model);
    }
}
