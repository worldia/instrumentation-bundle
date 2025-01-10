<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Doctrine\Propagation;

use Composer\InstalledVersions;
use Instrumentation\Tracing\Bridge\MainSpanContextInterface;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SemConv\ResourceAttributes;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Kernel;

class TraceContextInfoProvider implements TraceContextInfoProviderInterface
{
    /**
     * @var array<string,string>
     */
    private array|null $info = null;

    public function __construct(private ResourceInfo $resourceInfo, private MainSpanContextInterface|null $mainSpanContext = null, private RequestStack|null $requestStack = null)
    {
    }

    public function getTraceContext(): array
    {
        if (!$this->info) {
            $info = [];

            $traceContext = TraceContextPropagator::getInstance();
            $traceContext->inject($info);

            $info['action'] = $this->mainSpanContext?->getOperationName();
            $info['framework'] = 'symfony-'.Kernel::VERSION;

            try {
                $info['db_driver'] = \sprintf('doctrine/dbal-%s', InstalledVersions::getVersion('doctrine/dbal'));
            } catch (\Exception) {
                // Ignore
            }

            if ($this->resourceInfo->getAttributes()->has(ResourceAttributes::SERVICE_NAME)) {
                $info['application'] = $this->resourceInfo->getAttributes()->get(ResourceAttributes::SERVICE_NAME);
            }

            if ($controller = $this->requestStack?->getCurrentRequest()?->attributes->get('_controller')) {
                if (\is_string($controller)) {
                    $info['controller'] = str_replace('\\', '\\\\', $controller);
                }
            }

            $info['route'] = $this->requestStack?->getCurrentRequest()?->attributes->get('_route');

            $this->info = array_filter($info);
        }

        return $this->info;
    }
}
