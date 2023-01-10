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
        $hostname = parse_url($url, \PHP_URL_HOST);
        $protocol = parse_url($url, \PHP_URL_SCHEME);

        if (!empty($hostname) && !empty($protocol)) {
            $url = sprintf('%s://%s', $protocol, $hostname);
        }

        return sprintf('http.%s %s', strtolower($method), $url);
    }
}
