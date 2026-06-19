<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\OperationName;

interface PlatformOperationNameResolverInterface
{
    /**
     * @param array<string,mixed> $options
     *
     * @return string&non-empty-string
     */
    public function getOperationName(string $model, array $options): string;
}
