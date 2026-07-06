<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\AI\Platform;

use Instrumentation\Semantics\Attribute\PlatformAttributeProviderInterface;
use Instrumentation\Semantics\OperationName\PlatformOperationNameResolverInterface;
use Instrumentation\Tracing\AI\Sampling\OperationNameVoter;
use Instrumentation\Tracing\TracerAwareTrait;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;

final class TracingPlatform implements PlatformInterface
{
    use TracerAwareTrait;

    public function __construct(
        private readonly PlatformInterface $platform,
        TracerProviderInterface $tracerProvider,
        private readonly PlatformOperationNameResolverInterface $operationNameResolver,
        private readonly PlatformAttributeProviderInterface $attributeProvider,
        private readonly string $system,
        private readonly OperationNameVoter $voter,
    ) {
        $this->tracerProvider = $tracerProvider;
    }

    public function invoke(string|Model $model, array|string|object $input, array $options = []): DeferredResult
    {
        $modelName = $model instanceof Model ? $model->getName() : $model;

        if (!$this->voter->shouldTrace($modelName)) {
            return $this->platform->invoke($model, $input, $options);
        }

        $operationName = $this->operationNameResolver->getOperationName($modelName, $options);

        $span = $this->getTracer()
            ->spanBuilder($operationName)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttributes($this->attributeProvider->getAttributes($this->system, $modelName, $operationName))
            ->startSpan();

        // Activate the span for the inner invocation so the platform's own work — notably the outbound
        // HTTP request to the model provider — is recorded as a child of this span rather than a sibling.
        // The model client issues that request synchronously within invoke(), so this window covers it;
        // the span itself stays open past here (ended once the deferred result is consumed) via
        // TracingResultConverter.
        $scope = $span->activate();

        try {
            $deferredResult = $this->platform->invoke($model, $input, $options);
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR);
            $span->end();
            throw $e;
        } finally {
            $scope->detach();
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
