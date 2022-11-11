<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Instrumentation\EventSubscriber;

use Instrumentation\Semantics\Attribute\ServerRequestAttributeProviderInterface;
use Instrumentation\Semantics\Attribute\ServerResponseAttributeProviderInterface;
use Instrumentation\Semantics\OperationName\ServerRequestOperationNameResolverInterface;
use Instrumentation\Tracing\Instrumentation\MainSpanContextInterface;
use Instrumentation\Tracing\Instrumentation\TracerAwareTrait;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SDK\Trace\Span;
use OpenTelemetry\SDK\Trace\TracerProvider;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event;
use Symfony\Component\HttpKernel\KernelEvents;

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
            KernelEvents::REQUEST => [
                ['onRequestEvent', 512], // before all Symfony listeners
                ['onRouteResolved', 31], // right after Symfony\Component\HttpKernel\EventListener\RouterListener::onKernelRequest()
            ],
            KernelEvents::RESPONSE => ['onResponseEvent'],
            KernelEvents::FINISH_REQUEST => [['onFinishRequestEvent', -512]],
            KernelEvents::EXCEPTION => [['onExceptionEvent', -512]],
            KernelEvents::TERMINATE => [['onTerminate', -512]],
        ];
    }

    public function __construct(
        protected TracerProviderInterface $tracerProvider,
        protected MainSpanContextInterface $mainSpanContext,
        protected ServerRequestOperationNameResolverInterface $operationNameResolver,
        protected ServerRequestAttributeProviderInterface $requestAttributeProvider,
        protected ServerResponseAttributeProviderInterface $responseAttributeProvider,
    ) {
        $this->spans = new \SplObjectStorage();
        $this->scopes = new \SplObjectStorage();
    }

    public function onRequestEvent(Event\RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$this->serverSpan) {
            $startTime = $request->server->get('REQUEST_TIME_FLOAT'); // Float with microsecond precision
            $startTime = (int) ($startTime * 1000 * 1000 * 1000); // Convert to nanoseconds

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

        $span->updateName(sprintf('sf.controller.%s', $event->isMainRequest() ? 'main' : 'sub'));
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

        if (404 === $response->getStatusCode()) {
            $this->serverSpan?->updateName('http.error 404');
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

        if ($this->tracerProvider instanceof TracerProvider) {
            $this->tracerProvider->shutdown();
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
        if ($this->scopes->contains($request)) {
            $this->scopes[$request]->detach();
        }
    }
}
