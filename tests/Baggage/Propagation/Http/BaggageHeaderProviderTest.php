<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Baggage\Propagation\Http;

use Instrumentation\Baggage\Propagation\Http\BaggageHeaderProvider;
use OpenTelemetry\API\Baggage\Baggage;
use OpenTelemetry\Context\ScopeInterface;
use PHPUnit\Framework\TestCase;

class BaggageHeaderProviderTest extends TestCase
{
    private ScopeInterface|null $scope = null;

    protected function setUp(): void
    {
        $this->scope = Baggage::getBuilder()->set('foo', 'bar')->build()->activate();
    }

    protected function tearDown(): void
    {
        $this->scope->detach();
    }

    public function testItGetsBaggageHeader(): void
    {
        $this->assertEquals('baggage', BaggageHeaderProvider::getHeaderName());
        $this->assertEquals('foo=bar', BaggageHeaderProvider::getHeaderValue());
    }
}
