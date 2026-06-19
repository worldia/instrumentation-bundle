<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\AI\Platform;

use Instrumentation\Semantics\Attribute\PlatformAttributeProviderInterface;
use Instrumentation\Tracing\TracerAwareTrait;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;

final class TracingPlatform implements PlatformInterface
{
    use TracerAwareTrait;

    public function __construct(
        private readonly PlatformInterface $platform,
        TracerProviderInterface $tracerProvider,
        private readonly PlatformAttributeProviderInterface $attributeProvider,
        private readonly string $system,
    ) {
        $this->tracerProvider = $tracerProvider;
    }

    public function invoke(string $model, array|string|object $input, array $options = []): DeferredResult
    {
        $operationName = $options['extra']['operation_name'] ?? 'symfony_ai';

        $span = $this->getTracer()
            ->spanBuilder($operationName)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttributes($this->attributeProvider->getAttributes($this->system, $model, $operationName))
            ->startSpan();

        try {
            $deferredResult = $this->platform->invoke($model, $input, $options);
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR);
            $span->end();
            throw $e;
        }

        return new DeferredResult(
            new TracingResultConverter($deferredResult->getResultConverter(), $span),
            $deferredResult->getRawResult(),
            $options,
        );
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        return $this->platform->getModelCatalog();
    }
}
