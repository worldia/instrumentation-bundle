<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\Attribute;

use OpenTelemetry\SemConv\TraceAttributes;

class PlatformAttributeProvider implements PlatformAttributeProviderInterface
{
    public function getAttributes(string $system, string $model, string $operationName): array
    {
        return [
            TraceAttributes::GEN_AI_OPERATION_NAME => $operationName,
            TraceAttributes::GEN_AI_SYSTEM => $system,
            TraceAttributes::GEN_AI_REQUEST_MODEL => $model,
        ];
    }
}
