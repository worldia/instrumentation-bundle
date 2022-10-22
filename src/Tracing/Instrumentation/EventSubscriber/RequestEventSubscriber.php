<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Instrumentation\EventSubscriber;

use Instrumentation\Semantics\Attribute\ServerRequestAttributeProviderInterface;
use Instrumentation\Semantics\Attribute\ServerResponseAttributeProviderInterface;
use Instrumentation\Tracing\Instrumentation\MainSpanContextInterface;
use Instrumentation\Tracing\Instrumentation\TracerAwareTrait;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SDK\Trace\Span;
use OpenTelemetry\SemConv\TraceAttributes;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

class RequestEventSubscriber implements EventSubscriberInterface
{
    use TracerAwareTrait;

    private ?SpanInterface $serverSpan = null;
    private ?ScopeInterface $serverScope = null;

    /**
     * @var \SplObjectStorage<Request,SpanInterface>
     */
    private \SplObjectStorage $spans;

    /**
     * @var \SplObjectStorage<Request,ScopeInterface>
     */
    private \SplObjectStorage $scopes;

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onRequestEvent', 100]],
            KernelEvents::CONTROLLER => ['onControllerEvent'],
            KernelEvents::RESPONSE => ['onResponseEvent'],
            KernelEvents::FINISH_REQUEST => [['onFinishRequestEvent', -100]],
            KernelEvents::EXCEPTION => [['onExceptionEvent', -100]],
            KernelEvents::TERMINATE => [['onTerminate', -100]],
        ];
    }

    public function __construct(
        protected TracerProviderInterface $tracerProvider,
        protected RouterInterface $router,
        protected ServerRequestAttributeProviderInterface $requestAttributeProvider,
        protected ServerResponseAttributeProviderInterface $responseAttributeProvider,
        protected MainSpanContextInterface $mainSpanContext
    ) {
        $this->spans = new \SplObjectStorage();
        $this->scopes = new \SplObjectStorage();
    }

    public function onRequestEvent(Event\RequestEvent $event): void
    {
        $request = $event->getRequest();
        $startTime = $request->server->get('REQUEST_TIME_FLOAT'); // Float with microsecond precision

        if (!$this->serverSpan) {
            $this->serverSpan = $this->getTracer()->spanBuilder('server')
                ->setSpanKind(SpanKind::KIND_SERVER)
                ->setStartTimestamp((int) ($startTime * 1000 * 1000 * 1000))  // Convert to nanoseconds
                ->startSpan();

            $this->serverScope = $this->serverSpan->activate();

            $this->mainSpanContext->setMainSpan($this->serverSpan);
        }

        if ($event->isMainRequest()) {
            $attributes = $this->requestAttributeProvider->getAttributes($request);
            $attributes['sf.kernel_boot_duration'] = round(microtime(true) - $startTime, 3);

            $this->serverSpan->updateName(sprintf('http.%s %s', strtolower($request->getMethod()), $request->getPathInfo()));
            $this->serverSpan->setAttributes($attributes);
        }

        $this->startSpanForRequest($request, $event->isMainRequest());
    }

    public function onControllerEvent(Event\ControllerEvent $event): void
    {
        $request = $event->getRequest();

        $controller = $request->attributes->get('_controller');
        $routeAlias = $request->attributes->get('_route');

        $span = $this->getSpanForRequest($request);

        $span->updateName(sprintf('sf.controller.%s', $event->isMainRequest() ? 'main' : 'sub'));
        $span->setAttribute('sf.controller', $controller);
        $span->setAttribute('sf.route', $routeAlias);

        if ($routeAlias && $event->isMainRequest() && $route = $this->router->getRouteCollection()->get($routeAlias)) {
            /** @var non-empty-string $path */
            $path = $route->getPath();
            $this->serverSpan?->updateName(sprintf('http.%s %s', strtolower($request->getMethod()), $path));
            $this->serverSpan?->setAttribute(TraceAttributes::HTTP_ROUTE, $path);
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

        if ($response->getStatusCode() >= 500) {
            $this->serverSpan?->setStatus(StatusCode::STATUS_ERROR);
        }
    }

    public function onFinishRequestEvent(Event\FinishRequestEvent $event): void
    {
        $this->closeRequestScope($event->getRequest());
        $this->getSpanForRequest($event->getRequest())->end();
    }

    public function onTerminate(): void
    {
        $this->serverScope?->detach();
        $this->serverSpan?->end();
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
        if ($this->scopes->contains($request)) {
            $this->scopes[$request]->detach();
        }
    }
}
