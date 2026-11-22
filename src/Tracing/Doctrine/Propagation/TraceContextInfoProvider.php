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
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SemConv\Attributes\ServiceAttributes;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Kernel;

class TraceContextInfoProvider implements TraceContextInfoProviderInterface
{
    /**
     * @var array<string,string>
     */
    private array|null $info = null;

    /** The trace and main span the cached `$info` was built for. */
    private string|null $infoKey = null;

    public function __construct(private ResourceInfo $resourceInfo, private MainSpanContextInterface|null $mainSpanContext = null, private RequestStack|null $requestStack = null)
    {
    }

    public function getTraceContext(): array
    {
        // Cached per trace and main span, not for the life of the process: a worker serves many requests or
        // messages, and each query must carry its own traceparent, not the first one's.
        $key = Span::getCurrent()->getContext()->getTraceId().'/'.$this->mainSpanContext?->getMainSpan()->getContext()->getSpanId();

        if (!$this->info || $key !== $this->infoKey) {
            $this->infoKey = $key;
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

            if ($this->resourceInfo->getAttributes()->has(ServiceAttributes::SERVICE_NAME)) {
                $info['application'] = $this->resourceInfo->getAttributes()->get(ServiceAttributes::SERVICE_NAME);
            }

            if ($request = $this->requestStack?->getCurrentRequest()) {
                if ($controller = $request->attributes->get('_controller')) {
                    if (\is_string($controller)) {
                        $info['controller'] = str_replace('\\', '\\\\', $controller);
                    }
                }

                $info['route'] = $request->attributes->get('_route');
            }

            $this->info = array_filter($info);
        }

        return $this->info;
    }
}
