<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Tracing\Doctrine\Propagation;

use Composer\InstalledVersions;
use Instrumentation\Tracing\Doctrine\Propagation\TraceContextInfoProvider;
use Instrumentation\Tracing\Doctrine\Propagation\TraceContextInfoProviderInterface;
use OpenTelemetry\SDK\Common\Attribute\AttributesFactory;
use OpenTelemetry\SDK\Common\Attribute\AttributesInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SemConv\ResourceAttributes;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Kernel;

class TraceContextInfoProviderTest extends TestCase
{
    public function testItImplementsStampInterface(): void
    {
        $resourceInfo = $this->getInfo();

        $provider = new TraceContextInfoProvider($resourceInfo);
        $this->assertInstanceOf(TraceContextInfoProviderInterface::class, $provider);
    }

    private function getInfo(array $attributes = []): ResourceInfo
    {
        return ResourceInfo::create((new AttributesFactory())->builder($attributes)->build());
    }

    public function testItGetsMinimalTraceContext(): void
    {
        $resourceInfo = $this->getInfo();
        $provider = new TraceContextInfoProvider($resourceInfo);

        $this->assertEquals($this->getMinimalInfo(), $provider->getTraceContext());
    }

    public function testItGetsServiceName(): void
    {
        $attributes = $this->createMock(AttributesInterface::class);
        $attributes->expects($this->once())->method('has')->with(ResourceAttributes::SERVICE_NAME)->willReturn(true);
        $attributes->expects($this->once())->method('get')->with(ResourceAttributes::SERVICE_NAME)->willReturn('dummy-app');

        $provider = new TraceContextInfoProvider(ResourceInfo::create($attributes));

        $this->assertEquals(array_merge($this->getMinimalInfo(), [
            'application' => 'dummy-app',
        ]), $provider->getTraceContext());
    }

    public function testItGetsControllerAndRoute(): void
    {
        $parameters = $this->createMock(ParameterBag::class);
        $parameters->method('get')->willReturnCallback(function (string $param) {
            return match ($param) {
                '_controller' => 'Some\Controller',
                '_route' => 'some_route',
            };
        });

        $request = $this->createMock(Request::class);
        $request->attributes = $parameters;

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->expects($this->once())->method('getCurrentRequest')->willReturn($request);

        $attributes = $this->createMock(AttributesInterface::class);
        $provider = new TraceContextInfoProvider(ResourceInfo::create($attributes), null, $requestStack);

        $this->assertEquals(array_merge($this->getMinimalInfo(), [
            'route' => 'some_route',
            'controller' => 'Some\\\\Controller',
        ]), $provider->getTraceContext());
    }

    // public function it_gets_controller_and_route(ResourceInfo $info, AttributesInterface $attributes, RequestStack $requestStack, Request $request, ParameterBag $requestParameters): void
    // {
    //     $info->getAttributes()->willReturn($attributes);

    //     $this->beConstructedWith($info, null, $requestStack);

    //     $requestStack->getCurrentRequest()->willReturn($request);
    //     $request->attributes = $requestParameters;
    //     $requestParameters->get('_route')->willReturn('some_route');
    //     $requestParameters->get('_controller')->willReturn('Some\Controller');

    //     $this->getTraceContext()->shouldBeLike(array_merge($this->getMinimalInfo(), [
    //         'controller' => 'Some\\\\Controller',
    //         'route' => 'some_route',
    //     ]));
    // }

    private function getMinimalInfo(): array
    {
        return [
            'db_driver' => \sprintf('doctrine/dbal-%s', InstalledVersions::getVersion('doctrine/dbal')),
            'framework' => \sprintf('symfony-%s', Kernel::VERSION),
        ];
    }
}
