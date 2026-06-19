<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\AI\Toolbox;

use Instrumentation\Tracing\Tracing;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\TraceAttributes;
use OpenTelemetry\SemConv\TraceAttributeValues;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\Tool;

/**
 * Decorates a toolbox to create an `execute_tool` span around each tool call.
 *
 * Tools run in-process as part of the agent loop, so the span is INTERNAL and
 * nests under the `invoke_agent` span created by {@see \Instrumentation\Tracing\AI\Agent\TracingAgent}.
 */
final class TracingToolbox implements ToolboxInterface
{
    public function __construct(
        private readonly ToolboxInterface $toolbox,
    ) {
    }

    /**
     * @return Tool[]
     */
    public function getTools(): array
    {
        return $this->toolbox->getTools();
    }

    public function execute(ToolCall $toolCall): ToolResult
    {
        $span = Tracing::getTracer()
            ->spanBuilder('execute_tool '.$toolCall->getName())
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->startSpan();

        $span->setAttribute(TraceAttributes::GEN_AI_OPERATION_NAME, TraceAttributeValues::GEN_AI_OPERATION_NAME_EXECUTE_TOOL);
        $span->setAttribute(TraceAttributes::GEN_AI_TOOL_NAME, $toolCall->getName());
        $span->setAttribute(TraceAttributes::GEN_AI_TOOL_CALL_ID, $toolCall->getId());

        try {
            $result = $this->toolbox->execute($toolCall);
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
}
