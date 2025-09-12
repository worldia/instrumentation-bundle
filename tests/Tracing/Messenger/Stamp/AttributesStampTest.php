<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Tracing\Messenger\Stamp;

use Instrumentation\Tracing\Messenger\Stamp\AttributesStamp;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Stamp\StampInterface;

class AttributesStampTest extends TestCase
{
    public function testItImplementsStampInterface(): void
    {
        $stamp = new AttributesStamp();
        $this->assertInstanceOf(StampInterface::class, $stamp);
    }

    public function testItReturnsAttributes(): void
    {
        $stamp = new AttributesStamp(['foo' => 'bar']);

        $this->assertEquals(['foo' => 'bar'], $stamp->getAttributes());
    }
}
