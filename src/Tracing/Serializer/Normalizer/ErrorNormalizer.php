<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Serializer\Normalizer;

use Instrumentation\Tracing\TraceUrlGeneratorInterface;
use OpenTelemetry\SDK\Trace\Span;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ErrorNormalizer implements NormalizerInterface, CacheableSupportsMethodInterface
{
    public function __construct(private NormalizerInterface $decorated, private bool $addUrl = false, private ?TraceUrlGeneratorInterface $traceUrlGenerator = null)
    {
    }

    /**
     * @param array<mixed> $context
     *
     * @return array<mixed>|\ArrayObject<int|string,mixed>
     */
    public function normalize($exception, string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $data = $this->decorated->normalize($exception, $format, $context);

        if (false === \is_array($data)) {
            return $data;
        }

        $spanContext = Span::getCurrent()->getContext();

        $data['traceId'] = $spanContext->getTraceId();

        if ($this->addUrl && $this->traceUrlGenerator) {
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
        return $this->decorated instanceof CacheableSupportsMethodInterface && $this->decorated->hasCacheableSupportsMethod();
    }
}
