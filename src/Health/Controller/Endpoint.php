<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Health\Controller;

use Instrumentation\Health\HealtcheckInterface;
use Instrumentation\Metrics\MetricProviderInterface;
use Instrumentation\Metrics\RegistryInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Profiler\Profiler;

class Endpoint implements MetricProviderInterface
{
    public static function getProvidedMetrics(): array
    {
        return [
            'app_health' => [
                'type' => 'gauge',
                'help' => 'Global application health. 0: unhealthy, 1: degraded, 2: healthy',
            ],
        ];
    }

    /**
     * @param iterable<HealtcheckInterface> $checks
     */
    public function __construct(private ResourceInfo $resourceInfo, private iterable $checks, private ?RegistryInterface $registry = null, private ?Profiler $profiler = null)
    {
    }

    public function __invoke(): JsonResponse
    {
        if (null !== $this->profiler) {
            $this->profiler->disable();
        }

        $results = ['resource' => $this->resourceInfo->getAttributes()->toArray()];

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
        $statusInt = 2;
        if ($hasDegraded) {
            $status = HealtcheckInterface::DEGRADED;
            $statusInt = 1;
        }
        if ($hasDegradedCritical) {
            $status = HealtcheckInterface::UNHEALTHY;
            $statusInt = 0;
        }

        $results['status'] = $status;

        if (null !== $this->registry) {
            $this->registry->getGauge('app_health')->set($statusInt);
        }

        return new JsonResponse($results, HealtcheckInterface::UNHEALTHY === $status ? 500 : 200);
    }
}
