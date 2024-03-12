<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Serializer\Normalizer;

use Instrumentation\Tracing\TraceUrlGeneratorInterface;
use OpenTelemetry\SDK\Trace\Span;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

class ErrorNormalizer implements NormalizerInterface, SerializerAwareInterface
{
    public function __construct(private NormalizerInterface $decorated, private bool $addUrl = false, private TraceUrlGeneratorInterface|null $traceUrlGenerator = null)
    {
    }

    public function setSerializer(SerializerInterface $serializer): void
    {
        if ($this->decorated instanceof SerializerAwareInterface) {
            $this->decorated->setSerializer($serializer);
        }
    }

    /**
     * @return array<class-string, bool>
     */
    public function getSupportedTypes(string|null $format): array
    {
        return [
            FlattenException::class => __CLASS__ === self::class,
        ];
    }

    /**
     * @param array<mixed> $context
     *
     * @return array<mixed>|\ArrayObject<int|string,mixed>
     */
    public function normalize($exception, string|null $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
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

    /**
     * @param array<mixed> $context
     */
    public function supportsNormalization($data, string|null $format = null, array $context = []): bool
    {
        return $this->decorated->supportsNormalization($data, $format, $context);
    }
}
