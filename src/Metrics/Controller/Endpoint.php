<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Metrics\Controller;

use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\Profiler;

final class Endpoint
{
    public function __construct(private CollectorRegistry $registry, private ?Profiler $profiler = null)
    {
    }

    public function __invoke(): Response
    {
        if (null !== $this->profiler) {
            $this->profiler->disable();
        }

        $renderer = new RenderTextFormat();
        $result = $renderer->render($this->registry->getMetricFamilySamples());

        return new Response($result, 200, ['content-type' => 'text/plain']);
    }
}
