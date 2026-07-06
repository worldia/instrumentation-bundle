<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Metrics\AI\Platform;

use OpenTelemetry\API\Metrics\MeterProviderInterface;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;

/**
 * Decorates a platform to record the OTel gen_ai client metrics (semconv):
 * the token usage and operation duration histograms.
 *
 * Independent from {@see \Instrumentation\Tracing\AI\Platform\TracingPlatform}:
 * metrics and tracing are separate, separately-toggled concerns, so each wraps
 * the result converter on its own. The two decorators compose when both enabled.
 */
final class MeteringPlatform implements PlatformInterface
{
    private readonly AiMetricRecorder $recorder;

    public function __construct(
        private readonly PlatformInterface $platform,
        MeterProviderInterface $meterProvider,
        string $system,
    ) {
        $this->recorder = new AiMetricRecorder($meterProvider, $system);
    }

    public function invoke(string|Model $model, array|string|object $input, array $options = []): DeferredResult
    {
        $modelName = $model instanceof Model ? $model->getName() : $model;
        $start = hrtime(true);

        try {
            $deferredResult = $this->platform->invoke($model, $input, $options);
        } catch (\Throwable $e) {
            $this->recorder->recordDuration($modelName, (hrtime(true) - $start) / 1e9, $e);

            throw $e;
        }

        return new DeferredResult(
            new MeteringResultConverter($deferredResult->getResultConverter(), $this->recorder, $modelName, $start),
            $deferredResult->getRawResult(),
            $options,
        );
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        return $this->platform->getModelCatalog();
    }
}
