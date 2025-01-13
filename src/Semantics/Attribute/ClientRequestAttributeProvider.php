<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\Attribute;

use OpenTelemetry\SemConv\TraceAttributes;

class ClientRequestAttributeProvider implements ClientRequestAttributeProviderInterface
{
    /**
     * @param array<string> $capturedHeaders
     */
    public function __construct(private array $capturedHeaders = [])
    {
    }

    public function getAttributes(string $method, string $url, array $headers = []): array
    {
        $attributes = [
            TraceAttributes::HTTP_REQUEST_METHOD => strtoupper($method),
            TraceAttributes::URL_FULL => preg_replace('|(^https?://(?:.*):)(?:.*)(@.*)|', '$1<redacted>$2', $url) ?: $url,
        ];

        foreach ($this->capturedHeaders as $header) {
            if (!isset($headers[$header])) {
                continue;
            }
            if (\is_array($headers[$header])) {
                $headers[$header] = implode(',', $headers[$header]);
            }

            $attributes[\sprintf('http.request.header.%s', str_replace('-', '_', $header))] = $headers[$header];
        }

        $components = parse_url($url);

        if (!$components) {
            return $attributes;
        }

        $attributes += [
            TraceAttributes::URL_PATH => $components['path'] ?? null,
            TraceAttributes::URL_SCHEME => $components['scheme'] ?? null,
            TraceAttributes::URL_PORT => isset($components['port']) ? (string) $components['port'] : null,
        ];

        return array_filter($attributes);
    }
}
