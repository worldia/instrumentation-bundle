<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\OperationName;

use Symfony\AI\Agent\AgentInterface;

interface AgentOperationNameResolverInterface
{
    /**
     * @return string&non-empty-string
     */
    public function getOperationName(AgentInterface $agent): string;
}
