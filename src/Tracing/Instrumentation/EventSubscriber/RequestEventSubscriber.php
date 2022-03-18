<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Instrumentation\EventSubscriber;

use Instrumentation\Tracing\Propagation\Http\TraceHeadersProvider;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event;
use Symfony\Component\HttpKernel\KernelEvents;

class RequestEventSubscriber implements EventSubscriberInterface
{
    use TracerSubscriberTrait;

    /**
     * @var \SplObjectStorage<Request,SpanInterface>
     */
    private \SplObjectStorage $spans;

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onRequestEvent', 100]],
            KernelEvents::CONTROLLER => ['onControllerEvent'],
            KernelEvents::CONTROLLER_ARGUMENTS => ['onControllerArgumentsEvent'],
            KernelEvents::VIEW => ['onViewEvent'],
            KernelEvents::RESPONSE => ['onResponseEvent'],
            KernelEvents::FINISH_REQUEST => [['onFinishRequestEvent', -100]],
            KernelEvents::EXCEPTION => [['onExceptionEvent', -100]],
        ];
    }

    public function __construct(protected TracerProviderInterface $tracerProvider)
    {
        $this->spans = new \SplObjectStorage();
    }

    public function onRequestEvent(Event\RequestEvent $event): void
    {
        $this->startSpanForRequest($event->getRequest(), $event->isMainRequest());

        $this->addEvent($event);
    }

    public function onControllerEvent(Event\ControllerEvent $event): void
    {
        $this->addEvent($event);
    }

    public function onControllerArgumentsEvent(Event\ControllerArgumentsEvent $event): void
    {
        $this->addEvent($event);
    }

    public function onViewEvent(Event\ViewEvent $event): void
    {
        $this->addEvent($event);
    }

    public function onResponseEvent(Event\ResponseEvent $event): void
    {
        $this->addEvent($event);

        $event->getResponse()->headers->add(TraceHeadersProvider::getHeaders());
    }

    public function onFinishRequestEvent(Event\FinishRequestEvent $event): void
    {
        $this->addEvent($event);

        $this->getSpanForRequest($event->getRequest())->end();
    }

    public function onExceptionEvent(Event\ExceptionEvent $event): void
    {
        $this->getSpanForRequest($event->getRequest())->recordException($event->getThrowable())->end();
    }

    private function startSpanForRequest(Request $request, bool $activate): void
    {
        /** @var non-empty-string $uri */
        $uri = $request->getRequestUri();
        $span = $this->startSpan($uri);

        $span->setAttribute('http.method', $request->getMethod());
        $span->setAttribute('http.url', $request->getUri());
        $span->setAttribute('http.target', $request->getPathInfo());
        $span->setAttribute('http.host', $request->getHttpHost());
        $span->setAttribute('http.scheme', $request->getScheme());
        $span->setAttribute('http.proto', $request->getProtocolVersion());
        $span->setAttribute('http.user_agent', $request->headers->get('user-agent'));
        $span->setAttribute('http.request_content_length', $request->headers->get('content-length'));

        $span->setAttribute('sf.controller', $request->get('_controller'));
        $span->setAttribute('sf.route', $request->get('_route'));

        if ($activate) {
            $span->activate();
        }

        $this->spans[$request] = $span;
    }

    private function getSpanForRequest(Request $request): SpanInterface
    {
        return $this->spans[$request];
    }

    private function addEvent(Event\KernelEvent $event): void
    {
        $this->getSpanForRequest($event->getRequest())->addEvent(\get_class($event));
    }
}
