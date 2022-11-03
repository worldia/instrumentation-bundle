<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\OperationName\RoutePath;

interface RoutePathResolverInterface
{
    public function resolve(string $routeName): ?string;
}
