<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace spec\Instrumentation\Tracing\Propagation\Messenger;

use Instrumentation\Tracing\Propagation\Exception\ContextPropagationException;
use OpenTelemetry\API\Trace\NonRecordingSpan;
use OpenTelemetry\API\Trace\SpanContext;
use OpenTelemetry\API\Trace\TraceState;
use PhpSpec\ObjectBehavior;
use spec\Instrumentation\IsolateContext;

class TraceContextStampSpec extends ObjectBehavior
{
    use IsolateContext;

    public function let(): void
    {
        $this->forkMainContext();
    }

    public function letGo(): void
    {
        $this->restoreMainContext();
    }

    public function it_fails_when_there_is_no_parent_trace(): void
    {
        $this->shouldThrow(
            ContextPropagationException::becauseNoParentTrace(),
        )->duringInstantiation();
    }

    public function it_creates_stamp_without_state(): void
    {
        $span = new NonRecordingSpan(SpanContext::create(
            'b23f37322b169de7bcaf63b9f84b1427',
            '3c17130d40834256',
        ));
        $scope = $span->activate();

        $this->getTraceParent()->shouldBe('00-b23f37322b169de7bcaf63b9f84b1427-3c17130d40834256-00');
        $this->getTraceState()->shouldBe(null);

        $scope->detach();
        $span->end();
    }

    public function it_creates_stamp_with_state(): void
    {
        $span = new NonRecordingSpan(SpanContext::create(
            'b23f37322b169de7bcaf63b9f84b1427',
            '3c17130d40834256',
            SpanContext::TRACE_FLAG_DEFAULT,
            (new TraceState())->with('key', 'value'),
        ));
        $scope = $span->activate();

        $this->getTraceParent()->shouldBe('00-b23f37322b169de7bcaf63b9f84b1427-3c17130d40834256-00');
        $this->getTraceState()->shouldBe('key=value');

        $scope->detach();
        $span->end();
    }
}
