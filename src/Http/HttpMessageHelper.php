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
     * @param array<string,string[]> $headers
     */
    public static function formatHeadersForSpanAttribute(array $headers): string
    {
        $lines = [];
        foreach ($headers as $name => $values) {
            foreach ($values as $value) {
                $lines[] = sprintf('%s: %s', mb_strtolower($name), $value);
            }
        }

        return implode(\PHP_EOL, $lines);
    }
}
