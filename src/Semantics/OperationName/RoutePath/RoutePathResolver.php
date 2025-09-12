<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\OperationName\RoutePath;

class RoutePathResolver implements RoutePathResolverInterface
{
    public function __construct(private RouteCacheWarmer $routeCacheWarmer, private string $cacheDir)
    {
    }

    public function resolve(string $routeName): string|null
    {
        $routePathCacheFilename = $this->routeCacheWarmer->getCacheFile($this->cacheDir);

        if (!file_exists($routePathCacheFilename)) {
            $this->routeCacheWarmer->warmup($this->cacheDir);
        }

        $routePaths = require $routePathCacheFilename;

        return $routePaths[$routeName] ?? null;
    }
}
