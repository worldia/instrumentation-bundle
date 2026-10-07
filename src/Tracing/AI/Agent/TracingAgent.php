<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\AI\Agent;

use Instrumentation\Semantics\Attribute\AgentAttributeProviderInterface;
use Instrumentation\Semantics\OperationName\AgentOperationNameResolverInterface;
use Instrumentation\Tracing\AI\Sampling\OperationNameVoter;
use Instrumentation\Tracing\TracerAwareTrait;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\Context;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Execution\Cancellation;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\Execution\Update\Result;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;

/**
 * Decorates an agent to create an `invoke_agent` span around each execution (symfony/ai-agent >= 0.14).
 *
 * AgentInterface::call() returns a lazy Execution: the agent only runs while the execution is
 * consumed. The span therefore covers the consumption, as a child of the context active at call
 * time, and is the current span only while the agent itself runs (between two updates), never
 * while the caller handles an update. It is the parent of the CLIENT `chat` spans emitted by the
 * underlying platform and the INTERNAL `execute_tool` spans emitted by the toolbox.
 *
 * For symfony/ai-agent < 0.13 see {@see Legacy\TracingAgent}.
 */
final class TracingAgent implements AgentInterface
{
    use TracerAwareTrait;

    public function __construct(
        private readonly AgentInterface $agent,
        TracerProviderInterface $tracerProvider,
        private readonly AgentOperationNameResolverInterface $operationNameResolver,
        private readonly AgentAttributeProviderInterface $attributeProvider,
        private readonly OperationNameVoter $voter,
    ) {
        $this->tracerProvider = $tracerProvider;
    }

    public function call(string|MessageBag|UserMessage $input, array $options = []): Execution
    {
        if (!$this->voter->shouldTrace($this->agent->getName())) {
            return $this->agent->call($input, $options);
        }

        $spanBuilder = $this->getTracer()
            ->spanBuilder($this->operationNameResolver->getOperationName($this->agent))
            ->setParent(Context::getCurrent())
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setAttributes($this->attributeProvider->getAttributes($this->agent));

        try {
            $execution = $this->agent->call($input, $options);
        } catch (\Throwable $e) {
            $span = $spanBuilder->startSpan();
            self::recordError($span, $e);
            $span->end();

            throw $e;
        }

        $cancellation = new Cancellation();

        return new Execution(static function () use ($execution, $spanBuilder, $cancellation): \Generator {
            $span = $spanBuilder->startSpan();
            $updates = $cancellation->forward($execution)->getIterator();
            $inSpan = static function (\Closure $step) use ($span): mixed {
                $scope = $span->activate();

                try {
                    return $step();
                } finally {
                    $scope->detach();
                }
            };

            try {
                while ($inSpan($updates->valid(...))) {
                    $update = $updates->current();

                    if ($update instanceof Result) {
                        // The caller may stop consuming at the result (Execution::getResult() does).
                        $span->setStatus(StatusCode::STATUS_OK);
                    }

                    yield $update;

                    $inSpan($updates->next(...));
                }

                $span->setStatus(StatusCode::STATUS_OK);
            } catch (\Throwable $e) {
                self::recordError($span, $e);

                throw $e;
            } finally {
                $span->end();
            }
        }, $execution->isStreamed(), $cancellation);
    }

    public function getName(): string
    {
        return $this->agent->getName();
    }

    private static function recordError(SpanInterface $span, \Throwable $e): void
    {
        $span->recordException($e);
        $span->setStatus(StatusCode::STATUS_ERROR);
    }
}
