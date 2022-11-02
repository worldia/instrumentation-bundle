<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Routing;

use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\Routing\RouterInterface;

class RouteCacheWarmer implements CacheWarmerInterface
{
    private const ROUTE_PATHS_CACHE_FILE = 'route_paths.php';

    public function __construct(
        private RouterInterface $router,
    ) {
    }

    public function isOptional()
    {
        return true;
    }

    public function warmUp(string $cacheDir)
    {
        $routes = [];
        foreach ($this->router->getRouteCollection() as $name => $route) {
            $routes[$name] = $route->getPath();
        }

        $content = '<?php return '.var_export($routes, true).';'.\PHP_EOL;
        file_put_contents($this->getCacheFile($cacheDir), $content);

        return [];
    }

    public function getCacheFile(string $cacheDir): string
    {
        return $cacheDir.'/'.self::ROUTE_PATHS_CACHE_FILE;
    }
}
