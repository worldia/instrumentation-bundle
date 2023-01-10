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
use Prophecy\Argument;
use Psr\Log\LoggerInterface;
use spec\Instrumentation\IsolateContext;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Webmozart\Assert\Assert;

class MessengerEventSubscriberSpec extends ObjectBehavior
{
    use IsolateContext;

    private NonRecordingSpan $span;
    private ScopeInterface $scope;

    public function it_subsribes_to_events(): void
    {
        self::getSubscribedEvents()->shouldBe([
            SendMessageToTransportsEvent::class => [['onSend', 1000]],
            WorkerMessageReceivedEvent::class => [['onConsume', 1001]],
        ]);
    }

    public function let(LoggerInterface $logger): void
    {
        $this->forkMainContext();
        $this->beConstructedWith($logger);
        $this->activateSpan();
    }

    public function letGo(): void
    {
        $this->closeSpan();
        $this->restoreMainContext();
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

    public function it_silentely_logs_errors_as_warning_when_message_is_sent_without_being_able_to_propagate_trace_context(
        LoggerInterface $logger,
    ): void {
        $this->closeSpan();
        $originalEnveloppe = $this->createEnveloppeWithoutTraceContextStamp();
        $event = new SendMessageToTransportsEvent($originalEnveloppe, []);
        $expectedException = ContextPropagationException::becauseNoParentTrace();

        $this->onSend($event);

        Assert::same($event->getEnvelope(), $originalEnveloppe, 'The enveloppe has been modified.');
        $logger->warning(
            $expectedException->getMessage(),
            Argument::withEntry('exception', $expectedException),
        )->shouldHaveBeenCalled();
    }

    private function createEnveloppeWithoutTraceContextStamp(): Envelope
    {
        return new Envelope(new \stdClass());
    }

    public function it_adds_trace_context_stamp_when_message_is_sent(): void
    {
        $event = new SendMessageToTransportsEvent($this->createEnveloppeWithoutTraceContextStamp(), []);

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
