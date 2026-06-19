<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\Attribute;

interface PlatformAttributeProviderInterface
{
    /**
     * @return array<non-empty-string,string>
     */
    public function getAttributes(string $system, string $model, string $operationName): array;
}
