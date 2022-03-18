<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Serializer\Normalizer;

use ArrayObject;
use Instrumentation\Tracing\TraceUrlGeneratorInterface;
use OpenTelemetry\SDK\Trace\Span;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ProblemNormalizer;

class ErrorNormalizer implements NormalizerInterface, CacheableSupportsMethodInterface
{
    public function __construct(private ProblemNormalizer $decorated, private bool $addUrls = false, private ?TraceUrlGeneratorInterface $traceUrlGenerator = null)
    {
    }

    /**
     * @param array<mixed> $context
     *
     * @return array<mixed>|ArrayObject<int|string,mixed>
     */
    public function normalize($exception, string $format = null, array $context = []): array|string|int|float|bool|ArrayObject|null
    {
        $data = $this->decorated->normalize($exception, $format, $context);

        $spanContext = Span::getCurrent()->getContext();

        $data['traceId'] = $spanContext->getTraceId();
        $data['spanId'] = $spanContext->getSpanId();

        if ($this->addUrls && $this->traceUrlGenerator) {
            $data['logsUrl'] = $this->traceUrlGenerator->getLogsUrl($spanContext->getTraceId());
            $data['traceUrl'] = $this->traceUrlGenerator->getTraceUrl($spanContext->getTraceId());
        }

        return $data;
    }

    public function supportsNormalization($data, string $format = null): bool
    {
        return $this->decorated->supportsNormalization($data, $format);
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return $this->decorated->hasCacheableSupportsMethod();
    }
}
