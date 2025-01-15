<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Tracing\Messenger\Stamp;

use Instrumentation\Tracing\Messenger\Stamp\OperationNameStamp;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Stamp\StampInterface;

class OperationNameStampTest extends TestCase
{
    public function testItImplementsStampInterface(): void
    {
        $stamp = new OperationNameStamp('foo');
        $this->assertInstanceOf(StampInterface::class, $stamp);
    }

    public function testItReturnsAttributes(): void
    {
        $stamp = new OperationNameStamp('some-name');

        $this->assertEquals('some-name', $stamp->getOperationName());
    }
}
