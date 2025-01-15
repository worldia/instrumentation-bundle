<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Semantics\OperationName;

use Instrumentation\Semantics\OperationName\RoutePath\RoutePathResolverInterface;
use Instrumentation\Semantics\OperationName\RoutePathServerRequestOperationNameResolver;
use Instrumentation\Semantics\OperationName\ServerRequestOperationNameResolverInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

class RoutePathServerRequestOperationNameResolverTest extends TestCase
{
    public function testItImplementsMessageAttributeProviderInterface(): void
    {
        $this->assertTrue(is_a(RoutePathServerRequestOperationNameResolver::class, ServerRequestOperationNameResolverInterface::class, true));
    }

    public function testItResolvesOperationName(): void
    {
        $routePathResolver = $this->createMock(RoutePathResolverInterface::class);
        $routePathResolver->expects($this->once())->method('resolve')->with('some_route_name')->willReturn('/some/route/path');

        $request = $this->createMock(Request::class);
        $request->expects($this->once())->method('getMethod')->willReturn('GET');
        $request->attributes = $this->createMock(ParameterBag::class);
        $request->attributes->expects($this->once())->method('get')->with('_route')->willReturn('some_route_name');

        $resolver = new RoutePathServerRequestOperationNameResolver($routePathResolver);
        $operation = $resolver->getOperationName($request);

        $this->assertEquals('GET /some/route/path', $operation);
    }

    public function testItFallsBackToPathInfo(): void
    {
        $routePathResolver = $this->createMock(RoutePathResolverInterface::class);
        $routePathResolver->expects($this->once())->method('resolve')->with('some_route_name')->willReturn(null);

        $request = $this->createMock(Request::class);
        $request->expects($this->once())->method('getMethod')->willReturn('POST');
        $request->attributes = $this->createMock(ParameterBag::class);
        $request->attributes->expects($this->once())->method('get')->with('_route')->willReturn('some_route_name');
        $request->expects($this->once())->method('getPathInfo')->willReturn('/some/path');

        $resolver = new RoutePathServerRequestOperationNameResolver($routePathResolver);
        $operation = $resolver->getOperationName($request);

        $this->assertEquals('POST /some/path', $operation);
    }
}
