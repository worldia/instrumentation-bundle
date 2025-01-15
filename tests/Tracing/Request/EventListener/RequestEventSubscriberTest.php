<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Tracing\Request\EventListener;

use Instrumentation\Semantics\Attribute\ServerRequestAttributeProviderInterface;
use Instrumentation\Semantics\Attribute\ServerResponseAttributeProviderInterface;
use Instrumentation\Semantics\OperationName\ServerRequestOperationNameResolverInterface;
use Instrumentation\Tracing\Bridge\MainSpanContextInterface;
use Instrumentation\Tracing\Request\EventListener\RequestEventSubscriber;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ServerBag;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class RequestEventSubscriberTest extends TestCase
{
    public function testItImplementsEventSubscriberInterface()
    {
        $this->assertTrue(is_a(RequestEventSubscriber::class, EventSubscriberInterface::class, true));
    }

    public function testItSubscribesToRelevantEvents(): void
    {
        $events = RequestEventSubscriber::getSubscribedEvents();

        foreach ([
            KernelEvents::REQUEST,
            KernelEvents::RESPONSE,
            KernelEvents::FINISH_REQUEST,
            KernelEvents::EXCEPTION,
            KernelEvents::TERMINATE,
        ] as $eventName) {
            $this->assertArrayHasKey($eventName, $events);
        }
    }

    protected function expect(): array
    {
        $spans = new \ArrayObject();
        $exporter = new InMemoryExporter($spans);
        $spanProcessor = new SimpleSpanProcessor($exporter);
        $tracerProvider = new TracerProvider($spanProcessor);

        $mainSpanContext = $this->createMock(MainSpanContextInterface::class);

        $serverRequestOperationNameResolver = $this->createMock(ServerRequestOperationNameResolverInterface::class);
        $serverRequestAttributeProvider = $this->createMock(ServerRequestAttributeProviderInterface::class);
        $serverResponseAttributeProvider = $this->createMock(ServerResponseAttributeProviderInterface::class);

        $request = $this->createMock(Request::class);
        $request->attributes = $this->createMock(ParameterBag::class);
        $request->server = $this->createMock(ServerBag::class);
        $request->headers = $this->createMock(HeaderBag::class);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $response = $this->createMock(Response::class);
        $exception = $this->createMock(\RuntimeException::class);

        return [
            'spans' => $spans,
            RequestEventSubscriber::class => new RequestEventSubscriber(
                $tracerProvider,
                $mainSpanContext,
                $serverRequestOperationNameResolver,
                $serverRequestAttributeProvider,
                $serverResponseAttributeProvider
            ),

            ServerRequestOperationNameResolverInterface::class => $serverRequestOperationNameResolver,
            ServerResponseAttributeProviderInterface::class => $serverResponseAttributeProvider,

            RequestEvent::class => new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST),
            FinishRequestEvent::class => new FinishRequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST),
            ResponseEvent::class => new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response),
            TerminateEvent::class => new TerminateEvent($kernel, $request, $response),
            ExceptionEvent::class => new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception),

            Request::class => $request,
            Response::class => $response,
        ];
    }

    public function testItTracesRequestWhereTheRouteIsNotResolved(): void
    {
        [
            'spans' => $spans,
            RequestEventSubscriber::class => $subscriber,

            ServerRequestOperationNameResolverInterface::class => $serverRequestOperationNameResolver,

            RequestEvent::class => $requestEvent,
            ResponseEvent::class => $responseEvent,
            FinishRequestEvent::class => $finishRequestEvent,
            TerminateEvent::class => $terminateEvent,
        ] = $this->expect();

        $subscriber->onRequestEvent($requestEvent);
        $subscriber->onResponseEvent($responseEvent);
        $subscriber->onFinishRequestEvent($finishRequestEvent);
        $subscriber->onTerminate($terminateEvent);

        $this->assertCount(2, $spans);

        $this->assertEquals('server', $spans[1]->getName());
        $this->assertEquals(SpanKind::KIND_SERVER, $spans[1]->getKind());

        $this->assertEquals('request', $spans[0]->getName());
        $this->assertEquals(SpanKind::KIND_INTERNAL, $spans[0]->getKind());
    }

    public function testItTracesRequestWhereTheRouteIsResolved(): void
    {
        [
            'spans' => $spans,
            RequestEventSubscriber::class => $subscriber,

            ServerRequestOperationNameResolverInterface::class => $serverRequestOperationNameResolver,

            RequestEvent::class => $requestEvent,
            FinishRequestEvent::class => $finishRequestEvent,
            TerminateEvent::class => $terminateEvent,
        ] = $this->expect();

        $serverRequestOperationNameResolver->expects($this->once())->method('getOperationName')->willReturn('GET /hello');

        $subscriber->onRequestEvent($requestEvent);
        $subscriber->onRouteResolved($requestEvent);
        $subscriber->onFinishRequestEvent($finishRequestEvent);
        $subscriber->onTerminate($terminateEvent);

        $this->assertCount(2, $spans);

        $this->assertEquals('GET /hello', $spans[1]->getName());
        $this->assertEquals(SpanKind::KIND_SERVER, $spans[1]->getKind());

        $this->assertEquals('sf.controller.main', $spans[0]->getName());
        $this->assertEquals(SpanKind::KIND_INTERNAL, $spans[0]->getKind());
    }

    public function testItGetsResponseAttributes(): void
    {
        [
            'spans' => $spans,
            RequestEventSubscriber::class => $subscriber,

            ServerResponseAttributeProviderInterface::class => $serverResponseAttributeProvider,

            Response::class => $response,

            RequestEvent::class => $requestEvent,
            FinishRequestEvent::class => $finishRequestEvent,
            ResponseEvent::class => $responseEvent,
            TerminateEvent::class => $terminateEvent,
        ] = $this->expect();

        $serverResponseAttributeProvider->expects($this->once())->method('getAttributes')->with($response)->willReturn([
            'is_response' => true,
        ]);

        $subscriber->onRequestEvent($requestEvent);
        $subscriber->onResponseEvent($responseEvent);
        $subscriber->onFinishRequestEvent($finishRequestEvent);
        $subscriber->onTerminate($terminateEvent);

        $attributes = $spans[1]->getAttributes()->toArray();
        $this->assertArrayHasKey('is_response', $attributes);
        $this->assertTrue($attributes['is_response']);
    }

    public function testItUpdatesSpanOnException(): void
    {
        [
            'spans' => $spans,
            RequestEventSubscriber::class => $subscriber,

            Response::class => $response,

            RequestEvent::class => $requestEvent,
            ResponseEvent::class => $responseEvent,
            ExceptionEvent::class => $exceptionEvent,
            TerminateEvent::class => $terminateEvent,
        ] = $this->expect();

        $response->expects($this->any())->method('getStatusCode')->willReturn(500);

        $subscriber->onRequestEvent($requestEvent);
        $subscriber->onExceptionEvent($exceptionEvent);
        $subscriber->onResponseEvent($responseEvent);
        $subscriber->onTerminate($terminateEvent);

        $this->assertEquals('Error', $spans[1]->getStatus()->getCode());
        $this->assertEquals('Error', $spans[0]->getStatus()->getCode());
    }

    public function testItSetsSpanError(): void
    {
        [
            'spans' => $spans,
            RequestEventSubscriber::class => $subscriber,

            Response::class => $response,

            RequestEvent::class => $requestEvent,
            FinishRequestEvent::class => $finishRequestEvent,
            ResponseEvent::class => $responseEvent,
            TerminateEvent::class => $terminateEvent,
        ] = $this->expect();

        $response->expects($this->any())->method('getStatusCode')->willReturn(500);

        $subscriber->onRequestEvent($requestEvent);
        $subscriber->onResponseEvent($responseEvent);
        $subscriber->onFinishRequestEvent($finishRequestEvent);
        $subscriber->onTerminate($terminateEvent);

        $this->assertEquals('Error', $spans[1]->getStatus()->getCode());
    }

    public function testItUpdatesSpanOn404(): void
    {
        [
            'spans' => $spans,
            RequestEventSubscriber::class => $subscriber,

            Response::class => $response,

            RequestEvent::class => $requestEvent,
            FinishRequestEvent::class => $finishRequestEvent,
            ResponseEvent::class => $responseEvent,
            TerminateEvent::class => $terminateEvent,
        ] = $this->expect();

        $response->expects($this->any())->method('getStatusCode')->willReturn(404);

        $subscriber->onRequestEvent($requestEvent);
        $subscriber->onResponseEvent($responseEvent);
        $subscriber->onFinishRequestEvent($finishRequestEvent);
        $subscriber->onTerminate($terminateEvent);

        $this->assertEquals('Unset', $spans[1]->getStatus()->getCode());
        $this->assertEquals('http.error 404', $spans[1]->getName());
    }
}
