<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\OperationName;

class ClientRequestOperationNameResolver implements ClientRequestOperationNameResolverInterface
{
    public function getOperationName(string $method, string $url, string $peerName): string
    {
        return sprintf('http.%s %s', strtolower($method), $url);
    }
}
