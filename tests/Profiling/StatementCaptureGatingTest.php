<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Profiling;

use Instrumentation\Profiling\Encoding\PprofEncoder;
use Instrumentation\Profiling\Request\EventListener\RequestProfilingSubscriber;
use Instrumentation\Tracing\Bridge\MainSpanContext;
use Instrumentation\Tracing\Doctrine\Instrumentation\DBAL\QueryTally;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Tests\Instrumentation\Profiling\Stub\RecordingShipper;

/**
 * The statements of a request are captured when the request carries the on-demand token and `statements` is on: only that request,
 * never the sampled traffic, and never past its end, with or without the sampler (Excimer).
 */
final class StatementCaptureGatingTest extends TestCase
{
    public function testOnlyTheRequestCarryingTheTokenIsCapturedAndOnlyUntilItEnds(): void
    {
        $tally = new QueryTally(new MainSpanContext(), 0);
        $subscriber = $this->subscriber($tally, statements: true, active: true);

        $subscriber->onRequest($this->request(null));
        $this->assertFalse($tally->capturing(), 'no token: not captured, whatever the rate');
        $subscriber->onRequest($this->request('wrong'));
        $this->assertFalse($tally->capturing(), 'a wrong token');

        $subscriber->onRequest($this->request('secret'));
        $this->assertTrue($tally->capturing(), 'the token');
        $subscriber->onTerminate(new TerminateEvent($this->createStub(KernelInterface::class), Request::create('/'), new Response()));
        $this->assertFalse($tally->capturing(), 'off at the end of the request, with or without a profiler');

        $subscriber->onRequest($this->request('secret'));
        $subscriber->onRequest($this->request(null));
        $this->assertFalse($tally->capturing(), 'the next request of the worker starts clean');

        $subscriber->onRequest($this->request('secret'));
        $subscriber->reset();
        $this->assertFalse($tally->capturing(), 'a request that never terminated');
    }

    public function testNotUnlessAskedNorWhenProfilingIsInactiveNorForASubRequest(): void
    {
        $tally = new QueryTally(new MainSpanContext(), 0);

        $this->subscriber($tally, statements: false, active: true)->onRequest($this->request('secret'));
        $this->assertFalse($tally->capturing(), 'statements off');

        $this->subscriber($tally, statements: true, active: false)->onRequest($this->request('secret'));
        $this->assertFalse($tally->capturing(), 'profiling not active');

        $this->subscriber($tally, statements: true, active: true)->onRequest($this->request('secret', HttpKernelInterface::SUB_REQUEST));
        $this->assertFalse($tally->capturing(), 'a sub-request');
    }

    private function request(string|null $token, int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        $request = Request::create('/page');
        if (null !== $token) {
            $request->headers->set('X-Profile-Token', $token);
        }

        return new RequestEvent($this->createStub(KernelInterface::class), $request, $type);
    }

    private function subscriber(QueryTally $tally, bool $statements, bool $active): RequestProfilingSubscriber
    {
        return new RequestProfilingSubscriber(new RecordingShipper(), new PprofEncoder(), [], null, null, $active, 50, 1.0, 'wall', 'secret', 'X-Profile-Token', 'pyroscope.profile.id', 256, $tally, $statements);
    }
}
