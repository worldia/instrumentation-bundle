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
    public function __construct(private string|null $serverName = null, private array $capturedHeaders = [])
    {
    }

    public function getAttributes(Request $request): array
    {
        $attributes = [
            TraceAttributes::CLIENT_ADDRESS => $request->getClientIp(),
            TraceAttributes::SERVER_ADDRESS => $this->serverName ?: $request->getHost(),
            TraceAttributes::SERVER_PORT => (string) $request->getPort(),
            TraceAttributes::NETWORK_PROTOCOL_NAME => 'http',
            TraceAttributes::NETWORK_PROTOCOL_VERSION => substr((string) $request->getProtocolVersion(), 5),
            TraceAttributes::URL_SCHEME => $request->getScheme(),
            TraceAttributes::URL_DOMAIN => $request->getHost(),
            TraceAttributes::URL_PATH => $request->getPathInfo(),
            TraceAttributes::URL_QUERY => $request->getQueryString(),
            TraceAttributes::HTTP_REQUEST_METHOD => $request->getMethod(),
            TraceAttributes::HTTP_ROUTE => $request->attributes->get('_route'),
            TraceAttributes::USER_AGENT_ORIGINAL => $request->headers->get('user-agent', null),
            'http.request.header.host' => $request->headers->has('host') ? [$request->headers->get('host')] : null, // Per spec, only if host header is present
            'http.request.header.content_length' => $request->headers->has('content-length') ? [$request->headers->get('content-length', null)] : null,
        ];

        foreach ($this->capturedHeaders as $header) {
            $attributes[\sprintf('http.request.header.%s', str_replace('-', '_', $header))] = [(string) $request->headers->get($header, '')];
        }

        return array_filter($attributes);
    }
}
