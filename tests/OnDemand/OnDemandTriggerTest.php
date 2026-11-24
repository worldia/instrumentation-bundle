<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\OnDemand;

use Instrumentation\OnDemand\OnDemandTrigger;
use Instrumentation\Tracing\Bridge\MainSpanContext;
use Instrumentation\Tracing\Doctrine\Instrumentation\DBAL\QueryTally;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * The request an operator asked to look into carries the secret: decided when the main request starts, over when it ends, and what
 * costs something for one request (the statements of its trace) follows it and nothing else.
 */
final class OnDemandTriggerTest extends TestCase
{
    public function testOnlyTheMainRequestCarryingTheSecretIsAskedForAndOnlyUntilItEnds(): void
    {
        $trigger = new OnDemandTrigger('secret');

        $trigger->onRequest($this->event(null));
        $this->assertFalse($trigger->isOn(), 'no header');
        $trigger->onRequest($this->event('wrong'));
        $this->assertFalse($trigger->isOn(), 'a wrong secret');

        $trigger->onRequest($this->event('secret'));
        $this->assertTrue($trigger->isOn());
        $trigger->onRequest($this->event('wrong', HttpKernelInterface::SUB_REQUEST));
        $this->assertTrue($trigger->isOn(), 'a sub-request does not decide');
        $trigger->onTerminate(new TerminateEvent($this->createStub(HttpKernelInterface::class), Request::create('/'), new Response()));
        $this->assertFalse($trigger->isOn(), 'over with the request');

        $trigger->onRequest($this->event('secret'));
        $trigger->onRequest($this->event(null));
        $this->assertFalse($trigger->isOn(), 'the next request of a worker is decided afresh');

        $trigger->onRequest($this->event('secret'));
        $trigger->reset();
        $this->assertFalse($trigger->isOn(), 'a request that never terminated');
    }

    public function testNoSecretConfiguredMeansNoRequestIsAskedForAndTheHeaderIsConfigurable(): void
    {
        $none = new OnDemandTrigger('');
        $none->onRequest($this->event(''));
        $this->assertFalse($none->isOn());

        $custom = new OnDemandTrigger('secret', 'X-Look-Into-This');
        $request = Request::create('/page');
        $request->headers->set('X-Look-Into-This', 'secret');
        $custom->onRequest(new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
        $this->assertTrue($custom->isOn());
    }

    public function testTheStatementsAreCapturedOnlyWhenTheSettingIsOnAndTheRequestWasAskedFor(): void
    {
        $trigger = new OnDemandTrigger('secret');
        $main = new MainSpanContext();

        $off = new QueryTally($main, 0, $trigger, false, 100);
        $on = new QueryTally($main, 0, $trigger, true, 100);
        $this->assertFalse($on->capturing(), 'the request was not asked for');
        $this->assertFalse((new QueryTally($main))->capturing(), 'no trigger at all');

        $trigger->onRequest($this->event('secret'));
        $this->assertTrue($on->capturing());
        $this->assertFalse($off->capturing(), 'the setting is off');

        $trigger->reset();
        $this->assertFalse($on->capturing(), 'nothing carried past the request');
    }

    public function testItIsASubscriberBeforeAnythingElseOfTheBundle(): void
    {
        $events = OnDemandTrigger::getSubscribedEvents();

        $this->assertSame('onRequest', $events['kernel.request'][0][0]);
        $this->assertGreaterThan(1024, $events['kernel.request'][0][1], 'before the profiler, which starts at 1024');
    }

    private function event(string|null $secret, int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        $request = Request::create('/page');
        if (null !== $secret) {
            $request->headers->set('X-Profile-Token', $secret);
        }

        return new RequestEvent($this->createStub(HttpKernelInterface::class), $request, $type);
    }
}
