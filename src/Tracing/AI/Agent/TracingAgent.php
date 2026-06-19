<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\AI\Agent;

use Instrumentation\Semantics\Attribute\AgentAttributeProviderInterface;
use Instrumentation\Tracing\TracerAwareTrait;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * Decorates an agent to create an `invoke_agent` span around each call.
 *
 * The agent orchestrates in-process (running input/output processors and the
 * tool-calling loop), so the span is INTERNAL and acts as the parent of the
 * CLIENT `chat` spans emitted by the underlying platform and the INTERNAL
 * `execute_tool` spans emitted by the toolbox.
 */
final class TracingAgent implements AgentInterface
{
    use TracerAwareTrait;

    public function __construct(
        private readonly AgentInterface $agent,
        TracerProviderInterface $tracerProvider,
        private readonly AgentAttributeProviderInterface $attributeProvider,
    ) {
        $this->tracerProvider = $tracerProvider;
    }

    public function call(MessageBag $messages, array $options = []): ResultInterface
    {
        $span = $this->getTracer()
            ->spanBuilder('invoke_agent '.$this->agent->getName())
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setAttributes($this->attributeProvider->getAttributes($this->agent))
            ->startSpan();

        try {
            $result = $this->agent->call($messages, $options);
            $span->setStatus(StatusCode::STATUS_OK);

            return $result;
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR);

            throw $e;
        } finally {
            $span->end();
        }
    }

    public function getName(): string
    {
        return $this->agent->getName();
    }
}
