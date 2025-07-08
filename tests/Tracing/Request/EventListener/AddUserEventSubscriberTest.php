<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Tracing\Request\EventListener;

use Instrumentation\Tracing\Request\EventListener\AddUserEventSubscriber;
use OpenTelemetry\API\Trace\LocalRootSpan;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SDK\Trace\SpanLimitsBuilder;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\TraceAttributes;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class AddUserEventSubscriberTest extends TestCase
{
    public function testItImplementsEventSubscriberInterface()
    {
        $this->assertTrue(is_a(AddUserEventSubscriber::class, EventSubscriberInterface::class, true));
    }

    public function testItAddsUserAttributes()
    {
        $user = $this->createMock(UserInterface::class);
        $user->expects($this->any())->method('getUserIdentifier')->willReturn('customer@example.com');
        $user->expects($this->once())->method('getRoles')->willReturn(['ROLE_CUSTOMER']);

        $token = $this->createMock(TokenInterface::class);
        $token->expects($this->once())->method('getUser')->willReturn($user);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects($this->once())->method('getToken')->willReturn($token);

        $span = $this->getSpanBuilder()->startSpan();
        $scope = LocalRootSpan::store(Context::getCurrent(), $span)->activate();
        $subscriber = new AddUserEventSubscriber($tokenStorage);
        $subscriber->onRequestEvent($this->createRequestEvent('GET', '/hello'));
        $scope->detach();

        $this->assertEquals('customer@example.com', $span->getAttribute(TraceAttributes::USER_ID));
        $this->assertEquals(['ROLE_CUSTOMER'], $span->getAttribute(TraceAttributes::USER_ROLES));
    }

    private function getSpanBuilder(string $name = 'test'): SpanBuilderInterface
    {
        $spanLimitsBuilder = new SpanLimitsBuilder();
        if (method_exists($spanLimitsBuilder, 'retainGeneralIdentityAttributes')) {
            $spanLimitsBuilder->retainGeneralIdentityAttributes();
        }
        $spanLimits = $spanLimitsBuilder->build();

        return (new TracerProvider(
            spanLimits: $spanLimits,
        ))->getTracer('test')->spanBuilder($name);
    }

    private function createRequestEvent(
        string $method,
        string $path,
    ): RequestEvent {
        $request = Request::create($path, $method);

        $kernel = $this->createMock(KernelInterface::class);

        return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
