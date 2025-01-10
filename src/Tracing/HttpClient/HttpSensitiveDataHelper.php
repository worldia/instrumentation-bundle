<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\HttpClient;

use Nyholm\Psr7\Uri;

class HttpSensitiveDataHelper
{
    private const SENSITIVE_HEADERS = [
        'authorization',
        'proxy-authorization',
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
        $filtered = [];

        foreach ($headers as $header => $value) {
            if (\in_array(strtolower($header), self::SENSITIVE_HEADERS)) {
                continue;
            }
            $filtered[$header] = $value;
        }

        return $filtered;
    }
}
