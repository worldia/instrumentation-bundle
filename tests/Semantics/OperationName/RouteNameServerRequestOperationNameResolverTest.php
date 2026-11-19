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
use Symfony\Component\HttpFoundation\Request;

class RouteNameServerRequestOperationNameResolverTest extends TestCase
{
    public function testItImplementsMessageAttributeProviderInterface(): void
    {
        $this->assertTrue(is_a(RouteNameServerRequestOperationNameResolver::class, ServerRequestOperationNameResolverInterface::class, true));
    }

    public function testItResolvesOperationName(): void
    {
        $request = Request::create('/some/path', 'GET');
        $request->attributes->set('_route', 'some_route_name');

        $resolver = new RouteNameServerRequestOperationNameResolver();
        $operation = $resolver->getOperationName($request);

        $this->assertEquals('GET some_route_name', $operation);
    }

    public function testItFallsBackToPathInfo(): void
    {
        $request = Request::create('/some/path', 'POST');

        $resolver = new RouteNameServerRequestOperationNameResolver();
        $operation = $resolver->getOperationName($request);

        $this->assertEquals('POST /some/path', $operation);
    }
}
