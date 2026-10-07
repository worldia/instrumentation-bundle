<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Request\EventListener;

use Instrumentation\Semantics\Attribute\ServerRequestAttributeProviderInterface;
use Instrumentation\Semantics\Attribute\ServerResponseAttributeProviderInterface;
use Instrumentation\Semantics\OperationName\ServerRequestOperationNameResolverInterface;
use Instrumentation\Tracing\Bridge\ContextInitializer;
use Instrumentation\Tracing\Bridge\MainSpanContextInterface;
use Instrumentation\Tracing\TracerAwareTrait;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SDK\Trace\Span;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event;
use Symfony\Component\HttpKernel\KernelEvents;

class RequestEventSubscriber implements EventSubscriberInterface
{
    use TracerAwareTrait;

    private ScopeInterface|null $propagationScope = null;
    private ScopeInterface|null $serverScope = null;
    private SpanInterface|null $serverSpan = null;

    /**
     * @var \SplObjectStorage<Request,SpanInterface>
     */
    private \SplObjectStorage $spans;

    /**
     * @var \SplObjectStorage<Request,ScopeInterface>
     */
    private \SplObjectStorage $scopes;

    /**
     * @var \Closure(): bool
     */
    private \Closure $isConnectionAborted;

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['onRequestEvent', 512], // before all Symfony listeners
                ['onRouteResolved', 31], // right after Symfony\Component\HttpKernel\EventListener\RouterListener::onKernelRequest()
            ],
            KernelEvents::RESPONSE => ['onResponseEvent'],
            KernelEvents::FINISH_REQUEST => [['onFinishRequestEvent', -512]],
            KernelEvents::EXCEPTION => [['onExceptionEvent', -127]],
            KernelEvents::TERMINATE => [['onTerminate', -512]],
        ];
    }

    public function __construct(
        protected TracerProviderInterface $tracerProvider,
        protected MainSpanContextInterface $mainSpanContext,
        protected ServerRequestOperationNameResolverInterface $operationNameResolver,
        protected ServerRequestAttributeProviderInterface $requestAttributeProvider,
        protected ServerResponseAttributeProviderInterface $responseAttributeProvider,
        protected bool $flushAfterTerminate = true,
        \Closure|null $isConnectionAborted = null,
    ) {
        $this->spans = new \SplObjectStorage();
        $this->scopes = new \SplObjectStorage();
        $this->isConnectionAborted = $isConnectionAborted ?? static fn (): bool => 1 === connection_aborted();
    }

    public function onRequestEvent(Event\RequestEvent $event): void
    {
        $request = $event->getRequest();

        if ($event->isMainRequest()) {
            $startTime = $request->server->get('REQUEST_TIME_FLOAT'); // Float with microsecond precision
            $startTime = (int) ($startTime * 1000 * 1000 * 1000); // Convert to nanoseconds

            $this->propagationScope = ContextInitializer::fromRequest($request);
            $this->serverSpan = $this->getTracer()->spanBuilder('server')
                ->setSpanKind(SpanKind::KIND_SERVER)
                ->setStartTimestamp($startTime)
                ->startSpan();

            $this->serverScope = $this->serverSpan->activate();
            $this->mainSpanContext->setMainSpan($this->serverSpan);
        }

        $this->startSpanForRequest($request, $event->isMainRequest());
    }

    public function onRouteResolved(Event\RequestEvent $event): void
    {
        $request = $event->getRequest();

        $controller = $request->attributes->get('_controller');

        $span = $this->getSpanForRequest($request);

        $span->updateName(\sprintf('sf.controller.%s', $event->isMainRequest() ? 'main' : 'sub'));
        $span->setAttribute('sf.controller', $controller);

        if ($event->isMainRequest()) {
            $operationName = $this->operationNameResolver->getOperationName($request);
            $attributes = $this->requestAttributeProvider->getAttributes($request);

            $this->serverSpan?->updateName($operationName);
            $this->serverSpan?->setAttributes($attributes);
            $this->mainSpanContext->setOperationName($operationName);
        }
    }

    public function onResponseEvent(Event\ResponseEvent $event): void
    {
        $response = $event->getResponse();

        /** @var array<string&non-empty-string,string> $attributes */
        $attributes = $this->responseAttributeProvider->getAttributes($response);
        foreach ($attributes as $key => $value) {
            $this->serverSpan?->setAttribute($key, $value);
        }

        if (500 <= $response->getStatusCode()) {
            $this->serverSpan?->setStatus(StatusCode::STATUS_ERROR);
        }

        $routeWasResolved = $event->getRequest()->attributes->get('_controller', false);
        if (!$routeWasResolved && 404 === $response->getStatusCode()) {
            $this->serverSpan?->updateName('http.error 404');
        }
    }

    public function onFinishRequestEvent(Event\FinishRequestEvent $event): void
    {
        $this->closeRequestScope($event->getRequest());
        $this->getSpanForRequest($event->getRequest())->end();
        unset($this->spans[$event->getRequest()]); // Free memory
    }

    public function onTerminate(): void
    {
        $this->serverScope?->detach();
        // The client went away before the response was fully sent. Only observable when PHP keeps running
        // after a disconnect (ignore_user_abort=On): otherwise the request is aborted at the next write and
        // never reaches kernel.terminate, so this span is never ended nor exported.
        if (($this->isConnectionAborted)()) {
            $this->serverSpan?->setAttribute('http.client_aborted', true);
        }
        $this->serverSpan?->end();
        $this->propagationScope?->detach();

        // Long-running workers (FrankenPHP, RoadRunner) serve many requests per process: export now
        // rather than when the batch fills or the process exits.
        if ($this->flushAfterTerminate && method_exists($this->tracerProvider, 'forceFlush')) {
            $this->tracerProvider->forceFlush();
        }
    }

    public function onExceptionEvent(Event\ExceptionEvent $event): void
    {
        $this->closeRequestScope($event->getRequest());

        $span = $this->getSpanForRequest($event->getRequest());
        $span->recordException($event->getThrowable());
        $span->setStatus(StatusCode::STATUS_ERROR);
        $span->end();
    }

    private function startSpanForRequest(Request $request, bool $activate): void
    {
        $span = $this->startSpan('request');

        if ($activate) {
            $this->scopes[$request] = $span->activate();
        }

        $this->spans[$request] = $span;
    }

    private function getSpanForRequest(Request $request): SpanInterface
    {
        return $this->spans[$request] ?? $this->serverSpan ?: Span::getCurrent();
    }

    private function closeRequestScope(Request $request): void
    {
        if ($this->scopes->offsetExists($request)) {
            $this->scopes[$request]->detach();
            // An exception closes the scope before FINISH_REQUEST does: detach only once.
            $this->scopes->offsetUnset($request);
        }
    }
}
