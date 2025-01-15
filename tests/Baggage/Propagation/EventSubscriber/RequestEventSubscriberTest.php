<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Baggage\Propagation\EventSubscriber;

use Instrumentation\Baggage\Propagation\EventSubscriber\RequestEventSubscriber;
use OpenTelemetry\API\Baggage\Baggage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;

class RequestEventSubscriberTest extends TestCase
{
    public function testItImplementsEventSubscriberInterface()
    {
        $this->assertTrue(is_a(RequestEventSubscriber::class, EventSubscriberInterface::class, true));
    }

    public function testItSubscribesToRelevantEvents(): void
    {
        $events = RequestEventSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
    }

    public function testItInitializesContext(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $request = new Request(server: ['HTTP_BAGGAGE' => 'foo=bar']);

        $subscriber = new RequestEventSubscriber();

        $this->assertNull(Baggage::getCurrent()->getValue('foo'));

        $subscriber->onRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));

        $this->assertEquals('bar', Baggage::getCurrent()->getValue('foo'));

        $subscriber->onTerminate();
        $this->assertNull(Baggage::getCurrent()->getValue('foo'));
    }
}
