<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Routing;

use Symfony\Component\Routing\RouterInterface;

class RoutePathResolver implements RoutePathResolverInterface
{
    public function __construct(
        private RouterInterface $router,
        private string $cacheDir,
    ) {
    }

    public function resolve(string $routeName): ?string
    {
        // if the cache is not warmed up, then regenerate it and return the corresponding path
        if (!file_exists($routePathCacheFilename = $this->cacheDir.'/'.RouteCacheWarmer::ROUTE_PATHS_CACHE_FILE)) {
            return $this->router->getRouteCollection()->get($routeName)?->getPath();
        }

        // get the route path from the cache
        $routePaths = require $routePathCacheFilename;

        return $routePaths[$routeName] ?? null;
    }
}
