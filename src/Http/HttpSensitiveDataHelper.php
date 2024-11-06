<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Http;

use Nyholm\Psr7\Uri;

class HttpSensitiveDataHelper
{
    private const SENSITIVE_HEADERS = [
        'authorization',
        'Authorization',
        'proxy-authorization',
        'Proxy-Authorization',
    ];

    public static function filterUrl(string $url): string
    {
        $url = new Uri($url);
        $url = $url->withUserInfo('');

        return (string) $url;
    }

    /**
     * @param array<string,string[]|string> $headers
     *
     * @return array<string,string[]|string>
     */
    public static function filterHeaders(array $headers): array
    {
        return array_diff_key($headers, array_flip(self::SENSITIVE_HEADERS));
    }
}
