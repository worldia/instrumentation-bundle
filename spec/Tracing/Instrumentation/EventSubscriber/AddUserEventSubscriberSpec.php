<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace spec\Instrumentation\Tracing\Instrumentation\EventSubscriber;

use Instrumentation\Tracing\Instrumentation\MainSpanContextInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\SemConv\TraceAttributes;
use PhpSpec\ObjectBehavior;
use PhpSpec\Wrapper\Collaborator;
use Prophecy\Argument;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;

class AddUserEventSubscriberSpec extends ObjectBehavior
{
    private HttpKernelInterface|Collaborator $kernel;

    public function let(
        HttpKernelInterface $kernel
    ): void {
        $this->kernel = $kernel;
    }

    public function it_should_add_user_if_authenticated(
        SpanInterface $requestSpan,
        TokenStorageInterface $tokenStorage,
        UsernamePasswordToken $usernamePasswordToken,
        MainSpanContextInterface $mainSpanContext,
        UserInterface $user
    ): void {
        $user->getRoles()->willReturn(['ADMIN']);
        $user->getUserIdentifier()->willReturn('David');
        $usernamePasswordToken->getUser()->willReturn($user);
        $tokenStorage->getToken()->willReturn($usernamePasswordToken);
        $this->setupRequestSpan($requestSpan, $mainSpanContext);
        $this->beConstructedWith(
            $mainSpanContext,
            $tokenStorage
        );
        $mainRequestEvent = $this->createRequestEvent('/somewhere/{id}', Request::METHOD_PUT);
        $this->onRequestEvent($mainRequestEvent);
        $requestSpan->setAttribute(TraceAttributes::ENDUSER_ID, 'David')->shouldHaveBeenCalled();
        $requestSpan->setAttribute(TraceAttributes::ENDUSER_ROLE, ['ADMIN'])->shouldHaveBeenCalled();
    }

    private function setupRequestSpan(SpanInterface $requestSpan, MainSpanContextInterface $mainSpanContext)
    {
        $requestSpan->setAttribute(Argument::cetera())->willReturn($requestSpan);
        $mainSpanContext->getMainSpan()->willReturn($requestSpan);
    }

    private function createRequestEvent(
        string $path,
        string $method = Request::METHOD_GET,
    ): RequestEvent {
        $request = Request::create($path, $method);

        return new RequestEvent($this->kernel->getWrappedObject(), $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
