<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Propagation\Doctrine;

use Instrumentation\Tracing\Instrumentation\MainSpanContextInterface;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use Symfony\Component\HttpKernel\Kernel;

class TraceContextInfoProvider implements TraceContextInfoProviderInterface
{
    public function __construct(private ?MainSpanContextInterface $mainSpanContext = null, private ?string $serviceName = null)
    {
    }

    public function getTraceContext(): array
    {
        $info = [];

        $trace = TraceContextPropagator::getInstance();
        $trace->inject($info);

        $info['framework'] = 'symfony-'.Kernel::VERSION;
        $info['service'] = $this->serviceName;
        $info['operation'] = $this->mainSpanContext?->getOperationName();

        return array_filter($info);
    }
}
