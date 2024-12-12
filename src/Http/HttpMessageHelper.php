<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Http;

class HttpMessageHelper
{
    /**
     * @param array<string,string[]|string> $headers
     */
    public static function formatHeadersForSpanAttribute(array $headers): string
    {
        $headers = HttpSensitiveDataHelper::filterHeaders($headers);

        $lines = [];
        foreach ($headers as $name => $values) {
            if (\is_string($values)) {
                $values = [$values];
            }
            foreach ($values as $value) {
                $lines[] = \sprintf('%s: %s', mb_strtolower($name), $value);
            }
        }

        return implode(\PHP_EOL, $lines);
    }

    /**
     * @param array<string,string|string[]> $headers
     */
    public static function getContentType(array $headers): string|null
    {
        $headers = HttpSensitiveDataHelper::filterHeaders($headers);

        foreach ($headers as $name => $values) {
            if ('content-type' === strtolower($name)) {
                if (\is_array($values)) {
                    return array_shift($values);
                }

                return $values;
            }
        }

        return null;
    }
}
