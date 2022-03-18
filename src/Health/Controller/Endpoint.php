<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Health\Controller;

use Instrumentation\Health\HealtcheckInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Profiler\Profiler;

class Endpoint
{
    /**
     * @param iterable<HealtcheckInterface> $checks
     */
    public function __construct(private iterable $checks, private ?Profiler $profiler = null)
    {
    }

    public function __invoke(): JsonResponse
    {
        if (null !== $this->profiler) {
            $this->profiler->disable();
        }

        $results = [];

        $hasDegradedCritical = false;
        $hasDegraded = false;

        foreach ($this->checks as $check) {
            if (HealtcheckInterface::HEALTHY != $check->getStatus()) {
                $hasDegraded = true;
                if ($check->isCritical()) {
                    $hasDegradedCritical = true;
                }
            }

            $results['checks'][] = [
                'name' => $check->getName(),
                'description' => $check->getDescription(),
                'critical' => $check->isCritical(),
                'status' => $check->getStatus(),
                'message' => $check->getStatusMessage(),
            ];
        }

        $status = HealtcheckInterface::HEALTHY;
        if ($hasDegraded) {
            $status = HealtcheckInterface::DEGRADED;
        }
        if ($hasDegradedCritical) {
            $status = HealtcheckInterface::UNHEALTHY;
        }

        $results['status'] = $status;

        return new JsonResponse($results, HealtcheckInterface::UNHEALTHY === $status ? 500 : 200);
    }
}
