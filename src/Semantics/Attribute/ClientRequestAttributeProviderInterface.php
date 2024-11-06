<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\Attribute;

interface ClientRequestAttributeProviderInterface
{
    /**
     * @param array<string|array<string>> $headers
     *
     * @return array<non-empty-string,string|array<string>>
     */
    public function getAttributes(string $method, string $url, array $headers = []): array;
}
