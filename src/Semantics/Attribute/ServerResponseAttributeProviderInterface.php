<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\Attribute;

use Symfony\Component\HttpFoundation\Response;

interface ServerResponseAttributeProviderInterface
{
    /**
     * @return array<string,string|int>
     */
    public function getAttributes(Response $response): array;
}
