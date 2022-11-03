<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace spec\Instrumentation\Tracing\Instrumentation\EventSubscriber;

use Instrumentation\Semantics\Attribute\ServerRequestAttributeProvider;
use Instrumentation\Semantics\Attribute\ServerRequestAttributeProviderInterface;
use Instrumentation\Semantics\Attribute\ServerResponseAttributeProvider;
use Instrumentation\Semantics\Attribute\ServerResponseAttributeProviderInterface;
use Instrumentation\Semantics\OperationName\ServerRequestOperationNameResolverInterface;
use Instrumentation\Tracing\Instrumentation\MainSpanContext;
use Instrumentation\Tracing\TracerInterface;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\TraceAttributes;
use PhpSpec\ObjectBehavior;
use PhpSpec\Wrapper\Collaborator;
use Prophecy\Argument;
use spec\Instrumentation\IsolateContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RouteCollection;

class RequestEventSubscriberSpec extends ObjectBehavior
{
    use IsolateContext;

    private HttpKernelInterface|Collaborator $kernel;
    private array $requestAttributes = ['request' => 'attribute'];
    private array $responseAttributes = ['first' => 'attribute', 'second' => 'attribute'];
    private MainSpanContext $mainSpanContext;
    private RouteCollection $routeCollection;

    public function let(
        HttpKernelInterface $kernel,
        TracerInterface $tracer,
        SpanBuilderInterface $serverSpanBuilder,
        SpanInterface $serverSpan,
        ScopeInterface $serverScope,
        SpanBuilderInterface $requestSpanBuilder,
        SpanInterface $requestSpan,
        ScopeInterface $requestScope,
        TracerProviderInterface $tracerProvider,
        ServerRequestOperationNameResolverInterface $operationNameResolver,
        ServerRequestAttributeProviderInterface $requestAttributeProvider,
        ServerResponseAttributeProviderInterface $responseAttributeProvider,
    ): void {
        $this->kernel = $kernel;
        $operationNameResolver->getOperationName(Argument::type(Request::class))->willReturn('http.get /test/{id}');

        $requestAttributeProvider->getAttributes(Argument::type(Request::class))->willReturn(
            $this->requestAttributes,
        );
        $responseAttributeProvider->getAttributes(Argument::type(Response::class))->willReturn(
            $this->responseAttributes,
        );

        $tracerProvider->getTracer('io.opentelemetry.contrib.php')->willReturn($tracer);

        $tracer->spanBuilder('server')->willReturn($serverSpanBuilder);
        $serverSpanBuilder->setSpanKind(SpanKind::KIND_SERVER)->willReturn($serverSpanBuilder);
        $serverSpanBuilder->setStartTimestamp(Argument::type('int'))->willReturn($serverSpanBuilder);
        $serverSpanBuilder->startSpan()->willReturn($serverSpan);
        $serverSpan->activate()->willReturn($serverScope);
        $serverSpan->updateName(Argument::type('string'))->willReturn($serverSpan);
        $serverSpan->setAttributes($this->requestAttributes)->willReturn($serverSpan);
        $serverSpan->setAttribute(Argument::cetera())->willReturn($serverSpan);
        $serverSpan->setStatus(Argument::cetera())->willReturn($serverSpan);
        $serverScope->detach()->willReturn(0);

        $this->configureRequestSpanBuilder($tracer, $requestSpanBuilder, $requestSpan);
        $requestSpan->activate()->willReturn($requestScope);
        $requestScope->detach()->willReturn(0);

        $this->beConstructedWith(
            $tracerProvider,
            $this->mainSpanContext = new MainSpanContext(),
            $operationNameResolver,
            $requestAttributeProvider,
            $responseAttributeProvider,
        );
    }

    public function it_creates_server_span_and_request_span_when_receiving_request_for_the_first_time(
        SpanBuilderInterface $serverSpanBuilder,
        SpanInterface $serverSpan,
        SpanInterface $requestSpan,
    ): void {
        $mainRequestEvent = $this->createMainRequestEvent('/test/{id}', Request::METHOD_PUT);
        $startTime = 1656320753.1187;
        $mainRequestEvent->getRequest()->server->set('REQUEST_TIME_FLOAT', $startTime);

        $this->onRequestEvent($mainRequestEvent);

        $serverSpanBuilder->setStartTimestamp($startTime * 1000 ** 3)->shouldHaveBeenCalled();
        $serverSpan->activate()->shouldHaveBeenCalled();
        $serverSpan->updateName('http.put /test/{id}')->shouldHaveBeenCalled();
        $serverSpan->setAttributes($this->requestAttributes)->shouldHaveBeenCalled();
        expect($this->mainSpanContext->getMainSpan())->shouldBe($serverSpan);
        $requestSpan->activate()->shouldHaveBeenCalled();
    }

    public function it_only_creates_request_span_when_receiveing_sub_request(
        SpanInterface $serverSpan,
        TracerInterface $tracer,
        SpanBuilderInterface $subRequestSpanBuilder,
        SpanInterface $subRequestSpan,
    ): void {
        $mainRequestEvent = $this->createMainRequestEvent('/test/{id}', Request::METHOD_PUT);
        $subRequestEvent = $this->createSubRequestEvent('/sub-request', Request::METHOD_GET);
        $this->onRequestEvent($mainRequestEvent);
        $this->configureRequestSpanBuilder($tracer, $subRequestSpanBuilder, $subRequestSpan);

        $this->onRequestEvent($subRequestEvent);

        $serverSpan->activate()->shouldHaveBeenCalledOnce();
        $serverSpan->updateName('http.put /test/{id}')->shouldHaveBeenCalledOnce();
        expect($this->mainSpanContext->getMainSpan())->shouldBe($serverSpan);
        $subRequestSpanBuilder->startSpan()->shouldHaveBeenCalled();
        $subRequestSpan->activate()->shouldNotHaveBeenCalled();
    }

    public function it_updates_main_request_span_when_controller_is_resolved(SpanInterface $requestSpan): void
    {
        $mainRequestEvent = $this->createMainRequestEvent('/test/1');
        $request = $mainRequestEvent->getRequest();
        $request->attributes->add(['_controller' => 'Main::controller', '_route' => 'main_route']);
        $this->onRequestEvent($mainRequestEvent);

        $this->onRouteResolved($mainRequestEvent);

        $requestSpan->updateName('sf.controller.main')->shouldHaveBeenCalled();
        $requestSpan->setAttribute('sf.controller', 'Main::controller')->shouldHaveBeenCalled();
    }

    public function it_updates_server_span_when_controller_is_resolved_for_main_request_with_known_route(
        SpanInterface $serverSpan,
    ): void {
        $mainRequestEvent = $this->createMainRequestEvent('/test/1');
        $request = $mainRequestEvent->getRequest();
        $request->attributes->add(['_controller' => 'Main::controller', '_route' => 'main_route']);
        $this->onRequestEvent($mainRequestEvent);

        $this->onRouteResolved($mainRequestEvent);

        $serverSpan->updateName('http.get /test/{id}')->shouldHaveBeenCalled();
    }

    public function it_updates_sub_request_span_when_controller_is_resolved(SpanInterface $requestSpan): void
    {
        $mainRequestEvent = $this->createMainRequestEvent('/test/1');
        $request = $mainRequestEvent->getRequest();
        $request->attributes->add(['_controller' => 'Main::controller', '_route' => 'main_route']);
        $subRequestEvent = $this->createSubRequestEvent('/sub-request', Request::METHOD_GET);
        $request = $subRequestEvent->getRequest();
        $request->attributes->add(['_controller' => 'Sub::controller', '_route' => 'sub_route']);
        $this->onRequestEvent($mainRequestEvent);
        $this->onRequestEvent($subRequestEvent);
        $this->onRouteResolved($mainRequestEvent);
        $this->onRouteResolved($subRequestEvent);

        $requestSpan->updateName('sf.controller.sub')->shouldHaveBeenCalled();
        $requestSpan->setAttribute('sf.controller', 'Sub::controller')->shouldHaveBeenCalled();
    }

    public function it_does_not_update_server_span_when_controller_is_resolved_for_sub_request(
        SpanInterface $serverSpan,
    ): void {
        $mainRequestEvent = $this->createMainRequestEvent('/test/1');
        $request = $mainRequestEvent->getRequest();
        $request->attributes->add(['_controller' => 'Main::controller', '_route' => 'main_route']);
        $subRequestEvent = $this->createSubRequestEvent('/sub-request/1', Request::METHOD_GET);
        $request = $subRequestEvent->getRequest();
        $request->attributes->add(['_controller' => 'Sub::controller', '_route' => 'sub_route']);
        $this->onRequestEvent($mainRequestEvent);
        $this->onRequestEvent($subRequestEvent);

        $this->onRouteResolved($mainRequestEvent);

        $serverSpan->updateName('http.get /sub-request/{id}')->shouldNotHaveBeenCalled();
        $serverSpan->setAttribute(TraceAttributes::HTTP_ROUTE, 'sub-request')->shouldNotHaveBeenCalled();
    }

    public function it_adds_response_attributes_to_server_span(SpanInterface $serverSpan): void
    {
        $mainRequestEvent = $this->createMainRequestEvent('/test/1');
        $this->onRequestEvent($mainRequestEvent);

        $this->onResponseEvent($this->createResponseEvent($mainRequestEvent, 200));

        $serverSpan->setAttribute('first', 'attribute')->shouldHaveBeenCalled();
        $serverSpan->setAttribute('second', 'attribute')->shouldHaveBeenCalled();
    }

    public function it_sets_server_span_status_to_error_when_responses_status_code_is_greater_than_or_equal_to_500(
        SpanInterface $serverSpan,
    ): void {
        $mainRequestEvent = $this->createMainRequestEvent('/test/1');
        $this->onRequestEvent($mainRequestEvent);

        $this->onResponseEvent($this->createResponseEvent($mainRequestEvent, 500));

        $serverSpan->setStatus(StatusCode::STATUS_ERROR)->shouldHaveBeenCalled();
    }

    public function it_ends_span_for_the_request_once_finished(
        TracerInterface $tracer,
        ScopeInterface $requestScope,
        SpanInterface $requestSpan,
        SpanBuilderInterface $subRequestSpanBuilder,
        SpanInterface $subRequestSpan,
    ): void {
        $mainRequestEvent = $this->createMainRequestEvent('/test/{id}', Request::METHOD_PUT);
        $subRequestEvent = $this->createSubRequestEvent('/sub-request', Request::METHOD_GET);
        $this->onRequestEvent($mainRequestEvent);
        $this->configureRequestSpanBuilder($tracer, $subRequestSpanBuilder, $subRequestSpan);
        $this->onRequestEvent($subRequestEvent);

        $this->onFinishRequestEvent($this->createFinishRequestEvent($subRequestEvent));

        $requestSpan->end()->shouldNotHaveBeenCalled();
        $subRequestSpan->end()->shouldHaveBeenCalled();

        $this->onFinishRequestEvent($this->createFinishRequestEvent($mainRequestEvent));
        $requestScope->detach()->shouldHaveBeenCalled();
        $requestSpan->end()->shouldHaveBeenCalled();
    }

    public function it_ends_span_for_the_request_and_marks_it_in_error_when_an_exception_is_catched(
        ScopeInterface $requestScope,
        SpanInterface $requestSpan,
    ): void {
        $mainRequestEvent = $this->createMainRequestEvent('/test/1');
        $exceptionEvent = $this->createExceptionEvent($mainRequestEvent);
        $this->onRequestEvent($mainRequestEvent);

        $this->onExceptionEvent($exceptionEvent);

        $requestSpan->setStatus(StatusCode::STATUS_ERROR)->shouldHaveBeenCalled();
        $requestSpan->recordException($exceptionEvent->getThrowable())->shouldHaveBeenCalled();
        $requestScope->detach()->shouldHaveBeenCalled();
        $requestSpan->end()->shouldHaveBeenCalled();
    }

    public function it_ends_server_span_when_kernel_is_terminated(
        ScopeInterface $serverScope,
        SpanInterface $serverSpan,
    ): void {
        $mainRequestEvent = $this->createMainRequestEvent('/test/1');
        $this->onRequestEvent($mainRequestEvent);

        $this->onTerminate();

        $serverScope->detach()->shouldHaveBeenCalled();
        $serverSpan->end()->shouldHaveBeenCalled();
    }

    public function it_lets_context_unchanged_after_handling_a_request(ServerRequestOperationNameResolverInterface $operationNameResolver): void
    {
        $this->forkMainContext();
        $originalContext = clone Context::getCurrent();
        $this->beConstructedWith(
            new TracerProvider(),
            new MainSpanContext(),
            $operationNameResolver,
            new ServerRequestAttributeProvider(),
            new ServerResponseAttributeProvider(),
        );
        $mainRequestEvent = $this->createMainRequestEvent('/test/1');

        $this->onRequestEvent($mainRequestEvent);
        $this->onFinishRequestEvent($this->createFinishRequestEvent($mainRequestEvent));
        $this->onTerminate();

        expect(Context::getCurrent())->shouldBeLike($originalContext);

        $this->restoreMainContext();
    }

    private function createMainRequestEvent(string $path, string $method = Request::METHOD_GET): RequestEvent
    {
        return $this->createRequestEvent($path, $method, HttpKernelInterface::MAIN_REQUEST);
    }

    private function createSubRequestEvent(string $path, string $method = Request::METHOD_GET): RequestEvent
    {
        return $this->createRequestEvent($path, $method, HttpKernelInterface::SUB_REQUEST);
    }

    private function createRequestEvent(
        string $path,
        string $method = Request::METHOD_GET,
        int $requestType = HttpKernelInterface::MAIN_REQUEST,
    ): RequestEvent {
        $request = Request::create($path, $method);

        return new RequestEvent($this->kernel->getWrappedObject(), $request, $requestType);
    }

    private function configureRequestSpanBuilder(TracerInterface $tracer, SpanBuilderInterface $requestSpanBuilder, SpanInterface $requestSpan)
    {
        $tracer->spanBuilder('request')->willReturn($requestSpanBuilder);
        $requestSpanBuilder->setAttributes([])->willReturn($requestSpanBuilder);
        $requestSpanBuilder->startSpan()->willReturn($requestSpan);
        $requestSpan->updateName(Argument::type('string'))->willReturn($requestSpan);
        $requestSpan->setAttribute(Argument::cetera())->willReturn($requestSpan);
        $requestSpan->setStatus(Argument::cetera())->willReturn($requestSpan);
        $requestSpan->recordException(Argument::type(\Throwable::class))->willReturn($requestSpan);
    }

    private function createResponseEvent(RequestEvent $requestEvent, int $statusCode): ResponseEvent
    {
        return new ResponseEvent(
            $this->kernel->getWrappedObject(),
            $requestEvent->getRequest(),
            $requestEvent->getRequestType(),
            new Response('', $statusCode),
        );
    }

    private function createFinishRequestEvent(RequestEvent $requestEvent): FinishRequestEvent
    {
        return new FinishRequestEvent(
            $this->kernel->getWrappedObject(),
            $requestEvent->getRequest(),
            $requestEvent->getRequestType(),
        );
    }

    public function createExceptionEvent(RequestEvent $requestEvent): ExceptionEvent
    {
        return new ExceptionEvent(
            $this->kernel->getWrappedObject(),
            $requestEvent->getRequest(),
            $requestEvent->getRequestType(),
            new \Exception(),
        );
    }
}
