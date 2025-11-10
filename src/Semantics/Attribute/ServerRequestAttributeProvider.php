<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\Attribute;

use OpenTelemetry\SemConv\Attributes\ClientAttributes;
use OpenTelemetry\SemConv\Attributes\HttpAttributes;
use OpenTelemetry\SemConv\Attributes\NetworkAttributes;
use OpenTelemetry\SemConv\Attributes\ServerAttributes;
use OpenTelemetry\SemConv\Attributes\UrlAttributes;
use OpenTelemetry\SemConv\Attributes\UserAgentAttributes;
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
            ClientAttributes::CLIENT_ADDRESS => $request->getClientIp(),
            ServerAttributes::SERVER_ADDRESS => $this->serverName ?: $request->getHost(),
            ServerAttributes::SERVER_PORT => (string) $request->getPort(),
            NetworkAttributes::NETWORK_PROTOCOL_NAME => 'http',
            NetworkAttributes::NETWORK_PROTOCOL_VERSION => substr((string) $request->getProtocolVersion(), 5),
            UrlAttributes::URL_SCHEME => $request->getScheme(),
            UrlAttributes::URL_PATH => $request->getPathInfo(),
            UrlAttributes::URL_QUERY => $request->getQueryString(),
            HttpAttributes::HTTP_REQUEST_METHOD => $request->getMethod(),
            HttpAttributes::HTTP_ROUTE => $request->attributes->get('_route'),
            UserAgentAttributes::USER_AGENT_ORIGINAL => $request->headers->get('user-agent', null),
            'http.request.header.host' => $request->headers->has('host') ? [$request->headers->get('host')] : null, // Per spec, only if host header is present
            'http.request.header.content_length' => $request->headers->has('content-length') ? [$request->headers->get('content-length', null)] : null,
        ];

        foreach ($this->capturedHeaders as $header) {
            $attributes[\sprintf('http.request.header.%s', str_replace('-', '_', $header))] = [(string) $request->headers->get($header, '')];
        }

        return array_filter($attributes);
    }
}
