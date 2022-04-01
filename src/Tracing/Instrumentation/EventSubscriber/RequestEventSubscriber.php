<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Instrumentation\EventSubscriber;

use Instrumentation\Semantics\Attribute\RequestAttributeProviderInterface;
use Instrumentation\Semantics\Attribute\ResponseAttributeProviderInterface;
use Instrumentation\Tracing\Instrumentation\MainSpanContext;
use Instrumentation\Tracing\Instrumentation\TracerAwareTrait;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\SDK\Trace\Span;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

class RequestEventSubscriber implements EventSubscriberInterface
{
    use TracerAwareTrait;

    private ?SpanInterface $serverSpan = null;

    /**
     * @var \SplObjectStorage<Request,SpanInterface>
     */
    private \SplObjectStorage $spans;

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
        protected RequestAttributeProviderInterface $requestAttributeProvider,
        protected ResponseAttributeProviderInterface $responseAttributeProvider,
        protected MainSpanContext $mainSpanContext
    ) {
        $this->spans = new \SplObjectStorage();
    }

    public function onRequestEvent(Event\RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$this->serverSpan) {
            $startTime = $request->server->get('REQUEST_TIME_FLOAT'); // Float with microsecond precision
            $startTime = (int) ($startTime * 1000 * 1000 * 1000); // Convert to nanoseconds

            $this->serverSpan = $this->getTracer()->spanBuilder('server')
                ->setStartTimestamp($startTime)
                ->startSpan();
            $this->serverSpan->activate();

            $this->mainSpanContext->setMainSpan($this->serverSpan);
        }

        if ($event->isMainRequest()) {
            $attributes = $this->requestAttributeProvider->getAttributes($request);

            $this->serverSpan->updateName(sprintf('http.%s', strtolower($request->getMethod())));
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

        $span->updateName($controller);
        $span->setAttribute('sf.controller', $controller);
        $span->setAttribute('sf.route', $routeAlias);

        if ($routeAlias && $event->isMainRequest() && $route = $this->router->getRouteCollection()->get($routeAlias)) {
            /** @var non-empty-string $path */
            $path = $route->getPath();
            $this->serverSpan?->updateName($path);
        }
    }

    public function onResponseEvent(Event\ResponseEvent $event): void
    {
        /** @var array<string&non-empty-string,string> $attributes */
        $attributes = $this->responseAttributeProvider->getAttributes($event->getResponse());
        foreach ($attributes as $key => $value) {
            $this->serverSpan?->setAttribute($key, $value);
        }
    }

    public function onFinishRequestEvent(Event\FinishRequestEvent $event): void
    {
        $this->getSpanForRequest($event->getRequest())->end();
    }

    public function onTerminate(): void
    {
        $this->serverSpan?->end();
    }

    public function onExceptionEvent(Event\ExceptionEvent $event): void
    {
        $span = $this->getSpanForRequest($event->getRequest());
        $span->recordException($event->getThrowable());
        $span->end();
        // $this->getSpanForRequest($event->getRequest())->end();
    }

    private function startSpanForRequest(Request $request, bool $activate): void
    {
        $span = $this->startSpan('request');

        if ($activate) {
            $span->activate();
        }

        $this->spans[$request] = $span;
    }

    private function getSpanForRequest(Request $request): SpanInterface
    {
        return $this->spans[$request] ?? $this->serverSpan ?: Span::getCurrent();
    }
}
