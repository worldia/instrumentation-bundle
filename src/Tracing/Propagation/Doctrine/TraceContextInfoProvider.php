<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Propagation\Doctrine;

use Composer\InstalledVersions;
use Instrumentation\Tracing\Instrumentation\MainSpanContextInterface;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SemConv\ResourceAttributes;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Kernel;

class TraceContextInfoProvider implements TraceContextInfoProviderInterface
{
    private ?string $dbDriver = null;
    private string $framework;
    private ?string $serviceName = null;

    public function __construct(ResourceInfo $resourceInfo, private ?MainSpanContextInterface $mainSpanContext = null, private ?RequestStack $requestStack = null)
    {
        $this->framework = 'symfony-'.Kernel::VERSION;
        $this->serviceName = $resourceInfo->getAttributes()[ResourceAttributes::SERVICE_NAME] ?? 'app';

        try {
            $this->dbDriver = sprintf('doctrine/dbal-%s', InstalledVersions::getVersion('doctrine/dbal'));
        } catch (\Exception) {
            // Ignore
        }
    }

    public function getTraceContext(): array
    {
        $info = [];

        $traceContext = TraceContextPropagator::getInstance();
        $traceContext->inject($info);

        $info['db_driver'] = $this->dbDriver;
        $info['framework'] = $this->framework;
        $info['application'] = $this->serviceName;
        $info['action'] = $this->mainSpanContext?->getOperationName();
        $info['controller'] = $this->requestStack?->getCurrentRequest()?->attributes->get('_controller');
        $info['route'] = $this->requestStack?->getCurrentRequest()?->attributes->get('_route');

        return array_filter($info);
    }
}
