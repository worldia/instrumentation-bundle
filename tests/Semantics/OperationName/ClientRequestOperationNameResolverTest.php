<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Semantics\OperationName;

use Instrumentation\Semantics\OperationName\ClientRequestOperationNameResolver;
use Instrumentation\Semantics\OperationName\ClientRequestOperationNameResolverInterface;
use PHPUnit\Framework\TestCase;

class ClientRequestOperationNameResolverTest extends TestCase
{
    public function testItImplementsClientRequestAttributeProviderInterface(): void
    {
        $this->assertTrue(is_a(ClientRequestOperationNameResolver::class, ClientRequestOperationNameResolverInterface::class, true));
    }

    public function testItResolvesOperationName(): void
    {
        $resolver = new ClientRequestOperationNameResolver();
        $operation = $resolver->getOperationName('GET', 'https://www.google.com/search?foo=bar');

        $this->assertEquals('http.get https://www.google.com', $operation);
    }
}
