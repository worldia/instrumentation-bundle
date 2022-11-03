<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\Attribute;

use OpenTelemetry\SemConv\TraceAttributes;
use Symfony\Component\HttpFoundation\Request;

class ServerRequestAttributeProvider implements ServerRequestAttributeProviderInterface
{
    /**
     * @param array<string> $capturedHeaders
     */
    public function __construct(private ?string $serverName = null, private array $capturedHeaders = [])
    {
    }

    public function getAttributes(Request $request): array
    {
        $attributes = [
            TraceAttributes::HTTP_SERVER_NAME => $this->serverName,
            TraceAttributes::HTTP_METHOD => $request->getMethod(),
            TraceAttributes::HTTP_TARGET => $request->getRequestUri(),
            TraceAttributes::HTTP_HOST => $request->headers->get('host'), // Per spec, only if host header is present
            TraceAttributes::HTTP_ROUTE => $request->attributes->get('_route'),
            TraceAttributes::HTTP_SCHEME => $request->getScheme(),
            TraceAttributes::HTTP_FLAVOR => substr((string) $request->getProtocolVersion(), 5),
            TraceAttributes::HTTP_USER_AGENT => $request->headers->get('user-agent', null),
            TraceAttributes::HTTP_REQUEST_CONTENT_LENGTH => $request->headers->get('content-length', null),
            TraceAttributes::HTTP_CLIENT_IP => $request->getClientIp(),
            TraceAttributes::NET_HOST_PORT => (string) $request->getPort(),
        ];

        if ($this->serverName) {
            $attributes[TraceAttributes::HTTP_SERVER_NAME] = $this->serverName;
        } else {
            $attributes[TraceAttributes::NET_HOST_NAME] = $request->getHost();
        }

        foreach ($this->capturedHeaders as $header) {
            $attributes[sprintf('http.response.header.%s', str_replace('-', '_', $header))] = [(string) $request->headers->get($header, '')];
        }

        return array_filter($attributes);
    }
}
