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

    public function getAttributes(string $method, string $url, string $peerName, array $headers = []): array
    {
        $attributes = [
            TraceAttributes::PEER_SERVICE => $peerName,
            TraceAttributes::HTTP_SERVER_NAME => $peerName,
            TraceAttributes::HTTP_METHOD => strtoupper($method),
            TraceAttributes::HTTP_URL => $url,
        ];

        foreach ($this->capturedHeaders as $header) {
            if (!isset($headers[$header])) {
                continue;
            }
            if (\is_array($headers[$header])) {
                $headers[$header] = implode(',', $headers[$header]);
            }

            $attributes[sprintf('http.request.header.%s', str_replace('-', '_', $header))] = $headers[$header];
        }

        $components = parse_url($url);

        if (!$components) {
            return $attributes;
        }

        $attributes += [
            TraceAttributes::HTTP_TARGET => $components['path'] ?? null,
            TraceAttributes::HTTP_HOST => $components['host'] ?? null,
            TraceAttributes::HTTP_SCHEME => $components['scheme'] ?? null,
            TraceAttributes::NET_HOST_PORT => isset($components['port']) ? (string) $components['port'] : null,

            TraceAttributes::HTTP_FLAVOR => null,
            TraceAttributes::HTTP_USER_AGENT => null,
            TraceAttributes::HTTP_REQUEST_CONTENT_LENGTH => null,
            TraceAttributes::HTTP_CLIENT_IP => null,
        ];

        return array_filter($attributes);
    }
}
