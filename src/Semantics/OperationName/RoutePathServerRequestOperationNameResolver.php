<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\OperationName;

use Instrumentation\Semantics\OperationName\RoutePath\RoutePathResolverInterface;
use Symfony\Component\HttpFoundation\Request;

class RoutePathServerRequestOperationNameResolver implements ServerRequestOperationNameResolverInterface
{
    public function __construct(private RoutePathResolverInterface $routePathResolver)
    {
    }

    public function getOperationName(Request $request): string
    {
        $routeName = $request->attributes->get('_route');
        $path = null;

        if ($routeName) {
            $path = $this->routePathResolver->resolve($routeName);
        }

        if (!$path) {
            $path = $request->getPathInfo();
        }

        return sprintf('http.%s %s', strtolower($request->getMethod()), $path);
    }
}
