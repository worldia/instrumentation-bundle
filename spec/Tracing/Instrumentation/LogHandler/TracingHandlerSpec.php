<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace spec\Instrumentation\Tracing\Instrumentation\LogHandler;

use Instrumentation\Tracing\Instrumentation\MainSpanContextInterface;
use Monolog\DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use OpenTelemetry\API\Trace\SpanInterface;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class TracingHandlerSpec extends ObjectBehavior
{
    public function it_adds_event_from_all_channels(
        MainSpanContextInterface $mainSpanContext,
        SpanInterface $span,
    ): void {
        $this->beConstructedWith($mainSpanContext, Level::Info, []);

        $mainSpanContext->getMainSpan()->willReturn($span);
        $span->addEvent(Argument::any())->willReturn($span);

        $this->handle($this->createLogRecord('foo'));
        $this->handle($this->createLogRecord('bar'));

        $span->addEvent('Error from channel "foo"')->shouldHaveBeenCalled();
        $span->addEvent('Error from channel "bar"')->shouldHaveBeenCalled();
    }

    public function it_adds_event_from_specific_channel_only(
        MainSpanContextInterface $mainSpanContext,
        SpanInterface $span,
    ): void {
        $this->beConstructedWith($mainSpanContext, Level::Info, ['foo']);

        $mainSpanContext->getMainSpan()->willReturn($span);
        $span->addEvent(Argument::any())->willReturn($span);

        $this->handle($this->createLogRecord('foo'));
        $this->handle($this->createLogRecord('bar'));

        $span->addEvent('Error from channel "foo"')->shouldHaveBeenCalled();
        $span->addEvent('Error from channel "bar"')->shouldNotHaveBeenCalled();
    }

    public function it_ignores_event_from_specific_channel(
        MainSpanContextInterface $mainSpanContext,
        SpanInterface $span,
    ): void {
        $this->beConstructedWith($mainSpanContext, Level::Info, ['!foo']);

        $mainSpanContext->getMainSpan()->willReturn($span);
        $span->addEvent(Argument::any())->willReturn($span);

        $this->handle($this->createLogRecord('foo'));
        $this->handle($this->createLogRecord('bar'));

        $span->addEvent('Error from channel "foo"')->shouldNotHaveBeenCalled();
        $span->addEvent('Error from channel "bar"')->shouldHaveBeenCalled();
    }

    private function createLogRecord(string $chanel): LogRecord
    {
        return new LogRecord(
            new DateTimeImmutable(true),
            $chanel,
            Level::Error,
            'Error from channel "'.$chanel.'"',
        );
    }
}
