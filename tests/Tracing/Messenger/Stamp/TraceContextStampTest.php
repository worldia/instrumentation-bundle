<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Tracing\Messenger\Stamp;

use Instrumentation\Tracing\Messenger\Stamp\TraceContextStamp;
use OpenTelemetry\API\Trace\NonRecordingSpan;
use OpenTelemetry\API\Trace\SpanContext;
use OpenTelemetry\API\Trace\TraceFlags;
use OpenTelemetry\API\Trace\TraceState;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Stamp\StampInterface;

class TraceContextStampTest extends TestCase
{
    public function testItImplementsStampInterface(): void
    {
        $stamp = new TraceContextStamp();
        $this->assertInstanceOf(StampInterface::class, $stamp);
    }

    public function testItCreatesStampWithoutState(): void
    {
        $span = new NonRecordingSpan(SpanContext::create(
            'b23f37322b169de7bcaf63b9f84b1427',
            '3c17130d40834256',
        ));
        $scope = $span->activate();

        $stamp = new TraceContextStamp();
        $this->assertEquals('00-b23f37322b169de7bcaf63b9f84b1427-3c17130d40834256-00', $stamp->getTraceParent());
        $this->assertNull($stamp->getTraceState());

        $scope->detach();
        $span->end();
    }

    public function testItCreatesStampWithState(): void
    {
        $span = new NonRecordingSpan(SpanContext::create(
            'b23f37322b169de7bcaf63b9f84b1427',
            '3c17130d40834256',
            TraceFlags::DEFAULT,
            (new TraceState())->with('key', 'value'),
        ));
        $scope = $span->activate();

        $stamp = new TraceContextStamp();
        $this->assertEquals('00-b23f37322b169de7bcaf63b9f84b1427-3c17130d40834256-00', $stamp->getTraceParent());
        $this->assertEquals('key=value', $stamp->getTraceState());

        $scope->detach();
        $span->end();
    }
}
