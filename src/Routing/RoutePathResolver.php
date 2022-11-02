<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Routing;

class RoutePathResolver implements RoutePathResolverInterface
{
    public function __construct(
        private RouteCacheWarmer $routerCacheWarmer,
        private string $cacheDir,
    ) {
    }

    public function resolve(string $routeName): ?string
    {
        // if the cache is not warmed up, then regenerate it and return the corresponding path
        if (!file_exists($routePathCacheFilename = $this->routerCacheWarmer->getCacheFile($this->cacheDir))) {
            $this->routerCacheWarmer->warmup($this->cacheDir);
        }

        // get the route path from the cache
        $routePaths = require $routePathCacheFilename;

        return $routePaths[$routeName] ?? null;
    }
}
