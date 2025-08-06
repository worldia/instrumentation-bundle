<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace App\Controller;

use Instrumentation\Tracing\Bridge\MainSpanContextInterface;
use Instrumentation\Tracing\Bridge\TraceUrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as BaseController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

abstract class AbstractController extends BaseController
{
    public function __construct(
        #[Autowire(service: MainSpanContextInterface::class)]
        private readonly MainSpanContextInterface $mainSpanContext,

        #[Autowire(service: TraceUrlGeneratorInterface::class)]
        private readonly TraceUrlGeneratorInterface $traceUrlGenerator,
    ) {
    }

    protected function getTraceLink(): string
    {
        $traceId = $this->mainSpanContext->getMainSpan()->getContext()->getTraceId();
        $traceUrl = $this->traceUrlGenerator->getTraceUrl($traceId);

        return \sprintf('<a href="%s" target="_grafana">%s</a>', $traceUrl, $traceId);
    }
}
