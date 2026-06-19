<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\Attribute;

use OpenTelemetry\SemConv\TraceAttributes;
use OpenTelemetry\SemConv\TraceAttributeValues;
use Symfony\AI\Agent\AgentInterface;

class AgentAttributeProvider implements AgentAttributeProviderInterface
{
    public function getAttributes(AgentInterface $agent): array
    {
        return [
            TraceAttributes::GEN_AI_OPERATION_NAME => TraceAttributeValues::GEN_AI_OPERATION_NAME_INVOKE_AGENT,
            TraceAttributes::GEN_AI_AGENT_NAME => $agent->getName(),
        ];
    }
}
