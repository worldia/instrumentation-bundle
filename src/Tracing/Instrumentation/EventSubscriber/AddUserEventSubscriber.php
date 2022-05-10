<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Instrumentation\EventSubscriber;

use Instrumentation\Tracing\Instrumentation\MainSpanContext;
use Instrumentation\Tracing\Instrumentation\TracerAwareTrait;
use OpenTelemetry\SemConv\TraceAttributes;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class AddUserEventSubscriber implements EventSubscriberInterface
{
    use TracerAwareTrait;

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onRequestEvent', 0]],
        ];
    }

    public function __construct(private MainSpanContext $mainSpanContext, private ?TokenStorageInterface $tokenStorage = null)
    {
    }

    public function onRequestEvent(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $token = null;

        if (null !== $this->tokenStorage) {
            $token = $this->tokenStorage->getToken();
        }

        if ($token && $this->isTokenAuthenticated($token)) {
            $span = $this->mainSpanContext->getMainSpan();
            $user = $token->getUser();

            if ($user) {
                $span->setAttribute(TraceAttributes::ENDUSER_ID, $this->getUsername($user));
                $span->setAttribute(TraceAttributes::ENDUSER_ROLE, $user->getRoles());
            }
        }
    }

    /**
     * @param UserInterface|object|string $user
     */
    private function getUsername($user): ?string
    {
        if ($user instanceof UserInterface) {
            if (method_exists($user, 'getUserIdentifier')) {
                return $user->getUserIdentifier();
            }

            if (method_exists($user, 'getUsername')) {
                return $user->getUsername();
            }
        }

        if (\is_string($user)) {
            return $user;
        }

        if (\is_object($user) && method_exists($user, '__toString')) {
            return (string) $user;
        }

        return null;
    }

    private function isTokenAuthenticated(TokenInterface $token): bool
    {
        if (method_exists($token, 'isAuthenticated') && !$token->isAuthenticated(false)) {
            return false;
        }

        return null !== $token->getUser();
    }
}
