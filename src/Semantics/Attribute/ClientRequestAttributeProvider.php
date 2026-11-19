<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\Attribute;

use Instrumentation\Semantics\Url\UrlSanitizer;
use OpenTelemetry\SemConv\Attributes\HttpAttributes;
use OpenTelemetry\SemConv\Attributes\ServerAttributes;
use OpenTelemetry\SemConv\Attributes\UrlAttributes;

class ClientRequestAttributeProvider implements ClientRequestAttributeProviderInterface
{
    /**
     * @param array<string> $capturedHeaders
     */
    public function __construct(private array $capturedHeaders = [], private UrlSanitizer $urlSanitizer = new UrlSanitizer())
    {
    }

    public function getAttributes(string $method, string $url, array $headers = []): array
    {
        $attributes = [
            HttpAttributes::HTTP_REQUEST_METHOD => strtoupper($method),
            UrlAttributes::URL_FULL => $this->urlSanitizer->sanitize($url),
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
            UrlAttributes::URL_PATH => $components['path'] ?? null,
            UrlAttributes::URL_SCHEME => $components['scheme'] ?? null,
            ServerAttributes::SERVER_PORT => isset($components['port']) ? (string) $components['port'] : null,
        ];

        return array_filter($attributes);
    }
}
