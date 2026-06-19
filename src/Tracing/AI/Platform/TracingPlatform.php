<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\AI\Platform;

use Instrumentation\Tracing\Tracing;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;

final class TracingPlatform implements PlatformInterface
{
    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly string $system,
    ) {
    }

    public function invoke(string $model, array|string|object $input, array $options = []): DeferredResult
    {
        $operationName = $options['extra']['operation_name'] ?? 'symfony_ai';

        $span = Tracing::getTracer()
            ->spanBuilder($operationName)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->startSpan();

        $span->setAttribute('gen_ai.operation.name', $operationName);
        $span->setAttribute('gen_ai.system', $this->system);
        $span->setAttribute('gen_ai.request.model', $model);

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
