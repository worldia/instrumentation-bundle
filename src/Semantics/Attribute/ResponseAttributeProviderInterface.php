<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\Attribute;

use Symfony\Component\HttpFoundation\Response;

interface ResponseAttributeProviderInterface
{
    /**
     * @return array<string,string>
     */
    public function getAttributes(Response $response): array;
}
