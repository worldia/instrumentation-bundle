<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\Attribute;

use OpenTelemetry\SemConv\TraceAttributes;
use Symfony\Component\HttpFoundation\Response;

class ServerResponseAttributeProvider implements ServerResponseAttributeProviderInterface
{
    public function getAttributes(Response $response): array
    {
        return array_filter([
            TraceAttributes::HTTP_RESPONSE_STATUS_CODE => $response->getStatusCode(),

            // Irrelevant, never set by Symfony.
            // TraceAttributes::HTTP_RESPONSE_BODY_SIZE => $response->headers->get('content-length', null),
        ]);
    }
}
