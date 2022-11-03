<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\OperationName;

use Symfony\Component\HttpFoundation\Request;

class RouteNameServerRequestOperationNameResolver implements ServerRequestOperationNameResolverInterface
{
    public function getOperationName(Request $request): string
    {
        $routeName = $request->attributes->get('_route');

        if (!$routeName) {
            $routeName = $request->getPathInfo();
        }

        return sprintf('http.%s %s', strtolower($request->getMethod()), $routeName);
    }
}
