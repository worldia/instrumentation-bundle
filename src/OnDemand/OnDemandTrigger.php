<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\OnDemand;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Whether the request being handled is one an operator asked to look into: it carries the on-demand secret
 * (`instrumentation.on_demand.token`) in its header (`header`, `X-Profile-Token` by default). The one trigger the features that cost
 * something and are for a single request read, each with its own switch: the profile of the request ({@see \Instrumentation\Profiling}),
 * the SQL statements of its trace (`tracing.doctrine.statements.on_demand`), whatever comes next.
 *
 * Decided once, when the main request starts (before anything else of the bundle runs), and over when it ends or the worker is reset,
 * so a long-lived worker never carries it into the next request. Off for a command or a message: nothing carries a header.
 */
final class OnDemandTrigger implements EventSubscriberInterface, ResetInterface
{
    private bool $on = false;

    private readonly string $token;
    private readonly string $header;

    /** An unset environment variable with a `default::` fallback arrives as null: every setting has its default here. */
    public function __construct(string|null $token = '', string|null $header = 'X-Profile-Token')
    {
        $this->token = $token ?? '';
        $this->header = $header ?: 'X-Profile-Token';
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onRequest', 8192]],
            KernelEvents::TERMINATE => [['onTerminate', -8192]],
        ];
    }

    /** Whether the main request being handled carries the secret. */
    public function isOn(): bool
    {
        return $this->on;
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $this->on = $this->carries($event->getRequest());
    }

    public function onTerminate(TerminateEvent $event): void
    {
        $this->on = false;
    }

    public function reset(): void
    {
        $this->on = false;
    }

    private function carries(Request $request): bool
    {
        $supplied = $request->headers->get($this->header);

        return '' !== $this->token && \is_string($supplied) && hash_equals($this->token, $supplied);
    }
}
