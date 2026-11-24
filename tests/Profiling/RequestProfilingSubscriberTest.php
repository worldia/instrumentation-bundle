<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Profiling;

use Instrumentation\OnDemand\OnDemandTrigger;
use Instrumentation\Profiling\Encoding\PprofEncoder;
use Instrumentation\Profiling\Label\ProfileLabelProviderInterface;
use Instrumentation\Profiling\Label\RouteLabelProvider;
use Instrumentation\Profiling\Request\EventListener\RequestProfilingSubscriber;
use Instrumentation\Profiling\Shipping\ProfileShipperInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Tests\Instrumentation\Profiling\Stub\RecordingShipper;

/**
 * Which requests are sampled: none when off, a share by the request rate, and always the one carrying the on-demand token; and what
 * is shipped, once the request has terminated.
 */
#[RequiresPhpExtension('excimer')]
final class RequestProfilingSubscriberTest extends TestCase
{
    private OnDemandTrigger $trigger;

    /** @return iterable<string, array{bool, float, string, string|null, bool}> active, rate, configured token, supplied token, sampled */
    public static function cases(): iterable
    {
        yield 'not active, even with the token' => [false, 1.0, 's3cret', 's3cret', false];
        yield 'rate 1 samples every request' => [true, 1.0, '', null, true];
        yield 'rate 0 samples nothing' => [true, 0.0, 's3cret', null, false];
        yield 'rate 0 with the token samples that request' => [true, 0.0, 's3cret', 's3cret', true];
        yield 'rate 0 with a wrong token samples nothing' => [true, 0.0, 's3cret', 'guess', false];
        yield 'no token configured: the header means nothing' => [true, 0.0, '', '', false];
    }

    #[DataProvider('cases')]
    public function testWhichRequestsAreSampled(bool $active, float $rate, string $configured, string|null $supplied, bool $sampled): void
    {
        $subscriber = $this->subscriber($this->shipper(), $active, $rate, $configured);
        $request = Request::create('/page');
        if (null !== $supplied) {
            $request->headers->set('X-Profile-Token', $supplied);
        }

        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $this->trigger->onRequest($event); // decides first, as it is subscribed to
        $subscriber->onRequest($event);

        $this->assertSame($sampled, null !== (new \ReflectionProperty($subscriber, 'profiler'))->getValue($subscriber));
        $subscriber->reset(); // stops the timer a sampled case started
    }

    public function testASampledRequestIsShippedAfterItTerminatedWithItsLabels(): void
    {
        $shipper = $this->shipper();
        $subscriber = $this->subscriber($shipper, true, 1.0, '', [new RouteLabelProvider(), new class implements ProfileLabelProviderInterface {
            public function labels(Request $request): array
            {
                return ['channel' => (string) $request->headers->get('X-Channel'), 'empty' => ''];
            }
        }]);
        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/page');
        $request->attributes->set('_route', 'page');
        $request->headers->set('X-Channel', 'mock');

        $subscriber->onRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
        $end = hrtime(true) + 30_000_000;
        while (hrtime(true) < $end) {
            md5(random_bytes(8));
        }
        $terminate = new TerminateEvent($kernel, $request, new Response());
        $subscriber->onTerminate($terminate);
        $this->assertSame([], $shipper->shipped, 'nothing is shipped before the spans are flushed');
        $subscriber->onTerminated($terminate);

        $this->assertCount(1, $shipper->shipped);
        $this->assertSame(['route' => 'page', 'method' => 'GET', 'channel' => 'mock'], $shipper->shipped[0]['labels']);
        $this->assertNotSame('', $shipper->shipped[0]['pprof']);
    }

    public function testAnUnterminatedRequestIsAbandonedByTheReset(): void
    {
        $shipper = $this->shipper();
        $subscriber = $this->subscriber($shipper, true, 1.0, '');
        $subscriber->onRequest(new RequestEvent($this->createStub(HttpKernelInterface::class), Request::create('/'), HttpKernelInterface::MAIN_REQUEST));

        $subscriber->reset();

        $this->assertNull((new \ReflectionProperty($subscriber, 'profiler'))->getValue($subscriber));
        $this->assertSame([], $shipper->shipped);
        $this->assertGreaterThan(0, $shipper->reaped, 'the reset also finishes what a previous push left pending');
    }

    /** @param list<ProfileLabelProviderInterface> $providers */
    private function subscriber(ProfileShipperInterface $shipper, bool $active, float $rate, string $token, array $providers = []): RequestProfilingSubscriber
    {
        $this->trigger = new OnDemandTrigger($token);

        return new RequestProfilingSubscriber($shipper, new PprofEncoder(), $providers, null, null, $active, 50, $rate, 'wall', 'pyroscope.profile.id', 256, $this->trigger);
    }

    private function shipper(): RecordingShipper
    {
        return new RecordingShipper();
    }
}
