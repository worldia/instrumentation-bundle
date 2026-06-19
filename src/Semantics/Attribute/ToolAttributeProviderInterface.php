<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\Attribute;

use Symfony\AI\Platform\Result\ToolCall;

interface ToolAttributeProviderInterface
{
    /**
     * @return array<non-empty-string,string>
     */
    public function getAttributes(ToolCall $toolCall): array;
}
