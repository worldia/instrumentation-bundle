<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\OperationName;

class PlatformOperationNameResolver implements PlatformOperationNameResolverInterface
{
    public function getOperationName(string $model, array $options): string
    {
        $operationName = $options['extra']['operation_name'] ?? null;

        return \is_string($operationName) && '' !== $operationName ? $operationName : 'symfony_ai';
    }
}
