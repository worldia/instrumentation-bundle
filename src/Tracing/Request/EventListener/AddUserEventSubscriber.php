<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Request\EventListener;

use Instrumentation\Tracing\TracerAwareTrait;
use OpenTelemetry\API\Trace\LocalRootSpan;
use OpenTelemetry\SemConv\Incubating\Attributes\UserIncubatingAttributes;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Adds the `user.id` and `user.roles`attributes.
 *
 * @see https://opentelemetry.io/docs/specs/semconv/attributes-registry/user/
 *
 * Caution: Enabling this subscriber is not enough because they are dropped by default by
 * the `open-telemetry/sdk` (causing 'Dropped span attributes, links or events' warnings).
 * @see https://github.com/open-telemetry/opentelemetry-php/blob/83cddd9157438e7a72b7824708be36298c8e589f/src/SDK/Trace/SpanLimitsBuilder.php#L134.
 */
final class AddUserEventSubscriber implements EventSubscriberInterface
{
    use TracerAwareTrait;

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onRequestEvent', 0]],
        ];
    }

    public function __construct(private readonly TokenStorageInterface|null $tokenStorage = null)
    {
    }

    public function onRequestEvent(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $this->tokenStorage?->getToken();

        if (!$token) {
            return;
        }

        $span = LocalRootSpan::current();

        if ($user = $this->getUser($token)) {
            $span->setAttribute(UserIncubatingAttributes::USER_ID, $this->getUsername($user));

            $span->setAttribute(UserIncubatingAttributes::USER_ROLES, $user->getRoles());

            return;
        }

        $span->setAttribute(UserIncubatingAttributes::USER_ID, $token->getUserIdentifier());
    }

    protected function getUsername(UserInterface $user): string
    {
        return $user->getUserIdentifier();
    }

    private function getUser(TokenInterface $token): UserInterface|null
    {
        if (method_exists($token, 'isAuthenticated') && !$token->isAuthenticated(false)) {
            dump('it exists');

            return null;
        }

        return $token->getUser();
    }
}
