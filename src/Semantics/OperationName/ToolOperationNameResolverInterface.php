<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\OperationName;

use Symfony\AI\Platform\Result\ToolCall;

interface ToolOperationNameResolverInterface
{
    /**
     * @return string&non-empty-string
     */
    public function getOperationName(ToolCall $toolCall): string;
}
