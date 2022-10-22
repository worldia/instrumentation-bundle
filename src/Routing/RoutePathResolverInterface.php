<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Routing;

interface RoutePathResolverInterface
{
    public function resolve(string $routeName): ?string;
}
