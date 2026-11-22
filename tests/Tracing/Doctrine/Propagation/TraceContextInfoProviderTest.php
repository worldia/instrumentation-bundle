<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Tracing\Doctrine\Propagation;

use Composer\InstalledVersions;
use Instrumentation\Tracing\Bridge\MainSpanContext;
use Instrumentation\Tracing\Doctrine\Propagation\TraceContextInfoProvider;
use Instrumentation\Tracing\Doctrine\Propagation\TraceContextInfoProviderInterface;
use OpenTelemetry\SDK\Common\Attribute\AttributesFactory;
use OpenTelemetry\SDK\Common\Attribute\AttributesInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\Attributes\ServiceAttributes;
use PHPUnit\Framework\TestCase;
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
        $attributes->expects($this->once())->method('has')->with(ServiceAttributes::SERVICE_NAME)->willReturn(true);
        $attributes->expects($this->once())->method('get')->with(ServiceAttributes::SERVICE_NAME)->willReturn('dummy-app');

        $provider = new TraceContextInfoProvider(ResourceInfo::create($attributes));

        $this->assertEquals(array_merge($this->getMinimalInfo(), [
            'application' => 'dummy-app',
        ]), $provider->getTraceContext());
    }

    public function testItGetsControllerAndRoute(): void
    {
        $request = Request::create('/');
        $request->attributes->add(['_controller' => 'Some\Controller', '_route' => 'some_route']);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $provider = new TraceContextInfoProvider(ResourceInfo::create($this->createStub(AttributesInterface::class)), null, $requestStack);

        $this->assertEquals(array_merge($this->getMinimalInfo(), [
            'route' => 'some_route',
            'controller' => 'Some\\\\Controller',
        ]), $provider->getTraceContext());
    }

    public function testItRefreshesTheTraceContextWhenTheMainSpanChanges(): void
    {
        $provider = new TraceContextInfoProvider($this->getInfo(), $mainSpanContext = new MainSpanContext());
        $tracer = (new TracerProvider())->getTracer('test');

        $traceparents = [];
        foreach (['request 1', 'request 2'] as $name) {
            $span = $tracer->spanBuilder($name)->startSpan();
            $scope = $span->activate();
            $mainSpanContext->setMainSpan($span);

            // Two queries of the same request share the context; a later request gets its own.
            $traceparents[$name] = $provider->getTraceContext()['traceparent'];
            $this->assertSame($traceparents[$name], $provider->getTraceContext()['traceparent']);
            $this->assertStringContainsString($span->getContext()->getTraceId(), $traceparents[$name]);

            $scope->detach();
            $span->end();
            $mainSpanContext->reset();
        }

        $this->assertNotSame($traceparents['request 1'], $traceparents['request 2']);
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
