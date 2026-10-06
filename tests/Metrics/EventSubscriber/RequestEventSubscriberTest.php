<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Metrics\EventSubscriber;

use Instrumentation\Metrics\EventSubscriber\RequestEventSubscriber;
use OpenTelemetry\API\Common\Time\TestClock;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class RequestEventSubscriberTest extends TestCase
{
    public function testItFlushesOnTheFirstRequestThenAtMostOncePerInterval(): void
    {
        $clock = new TestClock();
        $meterProvider = $this->createMock(MeterProviderInterface::class);
        $subscriber = new RequestEventSubscriber($meterProvider, [], null, 10, $clock);

        $flushes = 0;
        $meterProvider->method('forceFlush')->willReturnCallback(static function () use (&$flushes): bool {
            ++$flushes;

            return true;
        });

        $subscriber->onTerminate($this->terminateEvent());
        $this->assertSame(1, $flushes);

        $clock->advanceSeconds(9);
        $subscriber->onTerminate($this->terminateEvent());
        $this->assertSame(1, $flushes);

        $clock->advanceSeconds(1);
        $subscriber->onTerminate($this->terminateEvent());
        $this->assertSame(2, $flushes);
    }

    public function testAZeroIntervalFlushesAfterEveryRequest(): void
    {
        $meterProvider = $this->createMock(MeterProviderInterface::class);
        $meterProvider->expects($this->exactly(2))->method('forceFlush')->willReturn(true);

        $subscriber = new RequestEventSubscriber($meterProvider, [], null, 0, new TestClock());

        $subscriber->onTerminate($this->terminateEvent());
        $subscriber->onTerminate($this->terminateEvent());
    }

    public function testBlacklistedRequestsRecordNothingButStillFlush(): void
    {
        $meterProvider = $this->createMock(MeterProviderInterface::class);
        $meterProvider->expects($this->never())->method('getMeter');
        $meterProvider->expects($this->once())->method('forceFlush')->willReturn(true);

        $subscriber = new RequestEventSubscriber($meterProvider, ['^/_healthz'], null, 10, new TestClock());

        $subscriber->onTerminate($this->terminateEvent('/_healthz'));
    }

    private function terminateEvent(string $path = '/'): TerminateEvent
    {
        return new TerminateEvent($this->createMock(HttpKernelInterface::class), Request::create($path), new Response());
    }
}
