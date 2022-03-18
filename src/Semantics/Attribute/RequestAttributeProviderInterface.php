<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\Attribute;

use OpenTelemetry\SemConv\TraceAttributes;
use Symfony\Component\HttpFoundation\Request;

interface RequestAttributeProviderInterface
{
    /**
     * @return array<TraceAttributes::HTTP_*,string>
     */
    public function getAttributes(Request $request): array;
}
