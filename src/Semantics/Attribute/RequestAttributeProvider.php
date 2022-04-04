<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\Attribute;

use OpenTelemetry\SemConv\TraceAttributes;
use Symfony\Component\HttpFoundation\Request;

class RequestAttributeProvider implements RequestAttributeProviderInterface
{
    public function getAttributes(Request $request): array
    {
        $attributes = [
            TraceAttributes::HTTP_METHOD => $request->getMethod(),
            // TraceAttributes::HTTP_URL => $request->getUri(),
            TraceAttributes::HTTP_TARGET => $request->getPathInfo(),
            TraceAttributes::HTTP_HOST => $request->getHttpHost(),
            TraceAttributes::HTTP_SCHEME => $request->getScheme(),
            TraceAttributes::HTTP_FLAVOR => substr((string) $request->getProtocolVersion(), 5),
            TraceAttributes::HTTP_USER_AGENT => $request->headers->get('user-agent', null),
            TraceAttributes::HTTP_REQUEST_CONTENT_LENGTH => $request->headers->get('content-length', null),
            // TraceAttributes::HTTP_REQUEST_CONTENT_LENGTH_UNCOMPRESSED,
        ];

        return array_filter($attributes);
    }
}
