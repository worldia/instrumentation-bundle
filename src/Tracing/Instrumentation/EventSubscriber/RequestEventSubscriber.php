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

            /** @var string&non-empty-string $name */
            $name = $request->getPathInfo();

            $this->serverSpan->updateName($name);
            $this->serverSpan->setAttributes($attributes);
        }

        $this->startSpanForRequest($request, $event->isMainRequest());
    }

    public function onControllerEvent(Event\ControllerEvent $event): void
    {
        $request = $event->getRequest();
        $controller = $event->getRequest()->attributes->get('_controller');

        $span = $this->getSpanForRequest($request);

        $span->updateName($controller);

        $span->setAttribute('sf.controller', $controller);
        $span->setAttribute('sf.route', $request->attributes->get('_route'));
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
        $this->getSpanForRequest($event->getRequest())->end();
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
