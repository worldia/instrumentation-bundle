<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\Attribute;

use Symfony\Component\HttpFoundation\Request;

interface ServerRequestAttributeProviderInterface
{
    /**
     * @return array<non-empty-string,string|array<string>>
     */
    public function getAttributes(Request $request): array;
}
