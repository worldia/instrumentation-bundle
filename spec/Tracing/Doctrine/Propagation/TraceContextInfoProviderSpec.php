<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace spec\Instrumentation\Tracing\Doctrine\Propagation;

use Composer\InstalledVersions;
use Instrumentation\Tracing\Doctrine\Propagation\TraceContextInfoProviderInterface;
use OpenTelemetry\SDK\Common\Attribute\AttributesInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SemConv\ResourceAttributes;
use PhpSpec\ObjectBehavior;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Kernel;

class TraceContextInfoProviderSpec extends ObjectBehavior
{
    public function let(ResourceInfo $info, AttributesInterface $attributes): void
    {
        $attributes->has(ResourceAttributes::SERVICE_NAME)->willReturn(false);
        $info->getAttributes()->willReturn($attributes);

        $this->beConstructedWith($info);
    }

    public function it_implements_interface(): void
    {
        $this->shouldBeAnInstanceOf(TraceContextInfoProviderInterface::class);
    }

    public function it_gets_trace_context(): void
    {
        $this->getTraceContext()->shouldBeLike($this->getMinimalInfo());
    }

    public function it_gets_service_name(ResourceInfo $info, AttributesInterface $attributes, RequestStack $requestStack, Request $request, ParameterBag $requestParameters): void
    {
        $attributes->has(ResourceAttributes::SERVICE_NAME)->willReturn(true);
        $attributes->get(ResourceAttributes::SERVICE_NAME)->willReturn('dummy-app');
        $info->getAttributes()->willReturn($attributes);

        $this->beConstructedWith($info);

        $this->getTraceContext()->shouldBeLike(array_merge($this->getMinimalInfo(), [
            'application' => 'dummy-app',
        ]));
    }

    public function it_gets_controller_and_route(ResourceInfo $info, AttributesInterface $attributes, RequestStack $requestStack, Request $request, ParameterBag $requestParameters): void
    {
        $info->getAttributes()->willReturn($attributes);

        $this->beConstructedWith($info, null, $requestStack);

        $requestStack->getCurrentRequest()->willReturn($request);
        $request->attributes = $requestParameters;
        $requestParameters->get('_route')->willReturn('some_route');
        $requestParameters->get('_controller')->willReturn('Some\Controller');

        $this->getTraceContext()->shouldBeLike(array_merge($this->getMinimalInfo(), [
            'controller' => 'Some\\\\Controller',
            'route' => 'some_route',
        ]));
    }

    private function getMinimalInfo(): array
    {
        return [
            'db_driver' => \sprintf('doctrine/dbal-%s', InstalledVersions::getVersion('doctrine/dbal')),
            'framework' => \sprintf('symfony-%s', Kernel::VERSION),
        ];
    }
}
