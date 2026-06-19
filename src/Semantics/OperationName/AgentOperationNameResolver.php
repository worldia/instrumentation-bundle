<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\OperationName;

use Symfony\AI\Agent\AgentInterface;

class AgentOperationNameResolver implements AgentOperationNameResolverInterface
{
    public function getOperationName(AgentInterface $agent): string
    {
        return \sprintf('invoke_agent %s', $agent->getName());
    }
}
