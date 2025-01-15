<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Semantics\OperationName;

use Instrumentation\Semantics\OperationName\RouteNameServerRequestOperationNameResolver;
use Instrumentation\Semantics\OperationName\ServerRequestOperationNameResolverInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

class RouteNameServerRequestOperationNameResolverTest extends TestCase
{
    public function testItImplementsMessageAttributeProviderInterface(): void
    {
        $this->assertTrue(is_a(RouteNameServerRequestOperationNameResolver::class, ServerRequestOperationNameResolverInterface::class, true));
    }

    public function testItResolvesOperationName(): void
    {
        $request = $this->createMock(Request::class);
        $request->expects($this->once())->method('getMethod')->willReturn('GET');
        $request->attributes = $this->createMock(ParameterBag::class);
        $request->attributes->expects($this->once())->method('get')->with('_route')->willReturn('some_route_name');

        $resolver = new RouteNameServerRequestOperationNameResolver();
        $operation = $resolver->getOperationName($request);

        $this->assertEquals('GET some_route_name', $operation);
    }

    public function testItFallsBackToPathInfo(): void
    {
        $request = $this->createMock(Request::class);
        $request->expects($this->once())->method('getMethod')->willReturn('POST');
        $request->attributes = $this->createMock(ParameterBag::class);
        $request->attributes->expects($this->once())->method('get')->with('_route')->willReturn(null);
        $request->expects($this->once())->method('getPathInfo')->willReturn('/some/path');

        $resolver = new RouteNameServerRequestOperationNameResolver();
        $operation = $resolver->getOperationName($request);

        $this->assertEquals('POST /some/path', $operation);
    }
}
