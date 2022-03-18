<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Propagation;

use OpenTelemetry\SDK\Trace\IdGeneratorInterface;
use Symfony\Contracts\Service\ResetInterface;

final class ForcableIdGenerator implements IdGeneratorInterface, ResetInterface
{
    private ?string $traceId = null;
    private ?string $spanId = null;

    public function __construct(private IdGeneratorInterface $decorated)
    {
    }

    public function setTraceId(string $traceId): void
    {
        $this->traceId = $traceId;
    }

    public function setSpanId(string $spanId): void
    {
        $this->spanId = $spanId;
    }

    public function generateTraceId(): string
    {
        if (null !== $this->traceId) {
            $traceId = $this->traceId;
            $this->traceId = null;

            return $traceId;
        }

        return $this->decorated->generateTraceId();
    }

    public function generateSpanId(): string
    {
        if (null !== $this->spanId) {
            $spanId = $this->spanId;
            $this->spanId = null;

            return $spanId;
        }

        return $this->decorated->generateSpanId();
    }

    public function reset(): void
    {
        $this->traceId = null;
        $this->spanId = null;
    }
}
