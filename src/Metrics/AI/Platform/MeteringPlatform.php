<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Metrics\AI\Platform;

use OpenTelemetry\API\Metrics\MeterProviderInterface;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;

/**
 * Decorates a platform to record the `gen_ai.client.token.usage` histogram
 * (OTel gen_ai metric semconv) from the token usage of each model call.
 *
 * Independent from {@see \Instrumentation\Tracing\AI\Platform\TracingPlatform}:
 * metrics and tracing are separate, separately-toggled concerns, so each wraps
 * the result converter on its own. The two decorators compose when both enabled.
 */
final class MeteringPlatform implements PlatformInterface
{
    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly MeterProviderInterface $meterProvider,
        private readonly string $system,
    ) {
    }

    public function invoke(string $model, array|string|object $input, array $options = []): DeferredResult
    {
        $deferredResult = $this->platform->invoke($model, $input, $options);

        return new DeferredResult(
            new MeteringResultConverter($deferredResult->getResultConverter(), $this->meterProvider, $this->system, $model),
            $deferredResult->getRawResult(),
            $options,
        );
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        return $this->platform->getModelCatalog();
    }
}
