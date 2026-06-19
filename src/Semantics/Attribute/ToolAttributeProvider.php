<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\Attribute;

use OpenTelemetry\SemConv\TraceAttributes;
use OpenTelemetry\SemConv\TraceAttributeValues;
use Symfony\AI\Platform\Result\ToolCall;

class ToolAttributeProvider implements ToolAttributeProviderInterface
{
    public function getAttributes(ToolCall $toolCall): array
    {
        return [
            TraceAttributes::GEN_AI_OPERATION_NAME => TraceAttributeValues::GEN_AI_OPERATION_NAME_EXECUTE_TOOL,
            TraceAttributes::GEN_AI_TOOL_NAME => $toolCall->getName(),
            TraceAttributes::GEN_AI_TOOL_CALL_ID => $toolCall->getId(),
        ];
    }
}
