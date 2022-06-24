<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace spec\Instrumentation\Tracing\Propagation\EventSubscriber;

use Instrumentation\Tracing\Propagation\Exception\ContextPropagationException;
use Instrumentation\Tracing\Propagation\Messenger\TraceContextStamp;
use OpenTelemetry\API\Trace\NonRecordingSpan;
use OpenTelemetry\API\Trace\SpanContext;
use OpenTelemetry\API\Trace\SpanContextKey;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ScopeInterface;
use PhpSpec\ObjectBehavior;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Webmozart\Assert\Assert;

class MessengerEventSubscriberSpec extends ObjectBehavior
{
    private NonRecordingSpan $span;
    private ScopeInterface $scope;

    public function it_subsribes_to_events(): void
    {
        self::getSubscribedEvents()->shouldBe([
            SendMessageToTransportsEvent::class => [['onSend', 1000]],
            WorkerMessageReceivedEvent::class => [['onConsume', 1001]],
        ]);
    }

    public function let(): void
    {
        // Needed to be able to undo the changes to the store when propagating a context trace
        // Otherwise we have conflict with other tests because of the global scope
        Context::storage()->fork(1);
        Context::storage()->switch(1);
        $this->activateSpan();
    }

    public function letGo(): void
    {
        $this->closeSpan();
        Context::storage()->destroy(1);
        Context::storage()->switch(1);
    }

    private function activateSpan(): void
    {
        $this->span = new NonRecordingSpan(SpanContext::create(
            '728217a6fe6cda718f10969d62f5bbc1',
            '6124380ad2dddeba',
        ));
        $this->scope = $this->span->activate();
    }

    private function closeSpan(): void
    {
        $this->scope->detach();
        $this->span->end();
    }

    public function it_fails_when_message_is_sent_without_being_able_to_propagate_trace_context(): void
    {
        $this->closeSpan();
        $event = new SendMessageToTransportsEvent($this->createEnveloppeWithoutTraceContextStamp());

        $this->shouldThrow(ContextPropagationException::becauseNoParentTrace())->duringOnSend($event);
    }

    private function createEnveloppeWithoutTraceContextStamp(): Envelope
    {
        return new Envelope(new \stdClass());
    }

    public function it_adds_trace_context_stamp_when_message_is_sent(): void
    {
        $event = new SendMessageToTransportsEvent($this->createEnveloppeWithoutTraceContextStamp());

        $this->onSend($event);

        Assert::notNull(
            $event->getEnvelope()->last(TraceContextStamp::class),
            'The TraceContextStamp was not added to the envelope.',
        );
    }

    public function it_does_nothing_when_receiving_message_without_trace_context_stamp(): void
    {
        $this->closeSpan();
        $event = new WorkerMessageReceivedEvent(
            $this->createEnveloppeWithoutTraceContextStamp(),
            'receiverName',
        );
        $previousContext = Context::getCurrent();

        $this->onConsume($event);

        Assert::same(Context::getCurrent(), $previousContext);
    }

    public function it_propagates_trace_context_when_receiving_message_with_trace_context_stamp(): void
    {
        $event = new WorkerMessageReceivedEvent(
            $this->createEnveloppeWithTraceContextStamp(),
            'receiverName',
        );
        $this->closeSpan();
        $previousContext = Context::getCurrent();

        $this->onConsume($event);

        Assert::notSame(Context::getCurrent(), $previousContext);
        Assert::notNull(
            Context::getCurrent()->get(SpanContextKey::instance()),
            'The context was not propagated.',
        );
    }

    private function createEnveloppeWithTraceContextStamp(): Envelope
    {
        return (new Envelope(new \stdClass()))->with(new TraceContextStamp());
    }
}
