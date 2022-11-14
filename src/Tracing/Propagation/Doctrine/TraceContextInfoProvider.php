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
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Kernel;

class TraceContextInfoProvider implements TraceContextInfoProviderInterface
{
    private ?string $dbDriver = null;
    private string $framework;

    public function __construct(private ?MainSpanContextInterface $mainSpanContext = null, private ?RequestStack $requestStack = null, private ?string $serviceName = null)
    {
        $this->framework = 'symfony-'.Kernel::VERSION;

        try {
            $this->dbDriver = InstalledVersions::getVersion('doctrine/dbal');
        } catch (\Exception) {
            // Ignore
        }
    }

    public function getTraceContext(): array
    {
        $info = [];

        $trace = TraceContextPropagator::getInstance();
        $trace->inject($info);

        $info['db_driver'] = $this->dbDriver;
        $info['framework'] = $this->framework;
        $info['app'] = $this->serviceName;
        $info['action'] = $this->mainSpanContext?->getOperationName();
        $info['controller'] = $this->requestStack?->getCurrentRequest()?->attributes->get('_controller');
        $info['route'] = $this->requestStack?->getCurrentRequest()?->attributes->get('_route');

        return array_filter($info);
    }
}
