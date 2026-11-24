<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Profiling\Label;

use Symfony\Component\HttpFoundation\Request;

/** The route name and the method: what a flame graph is grouped by. */
final class RouteLabelProvider implements ProfileLabelProviderInterface
{
    public function labels(Request $request): array
    {
        $route = $request->attributes->get('_route');

        return ['route' => \is_string($route) && '' !== $route ? $route : 'unrouted', 'method' => $request->getMethod()];
    }
}
