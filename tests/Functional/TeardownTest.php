<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Functional;

use Instrumentation\InstrumentationBundle;
use Instrumentation\Tracing\Tracing;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\SDK\Common\Export\InMemoryStorageManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Teardown must be best-effort: InstrumentationBundle::__destruct() must never force-instantiate a
 * telemetry provider that went unused this process (which would lazily build an OTLP exporter for
 * nothing), and an exception must never escape the destructor. When telemetry *is* used, shutdown
 * must still flush it.
 */
final class TeardownTest extends KernelTestCase
{
    protected function setUp(): void
    {
        // Force a fresh compile so this genuinely exercises DI compilation.
        (new Filesystem())->remove(sys_get_temp_dir().'/instrumentation-bundle-tests');
    }

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testTeardownWithoutTelemetryIsInertAndSafe(): void
    {
        self::bootKernel();

        // The container the bundle's __destruct actually operates on.
        $container = self::$kernel->getContainer();

        // boot() must no longer eagerly build the tracer provider (nor any other provider).
        $this->assertFalse($container->initialized(TracerProviderInterface::class));
        $this->assertFalse($container->initialized(MeterProviderInterface::class));

        /** @var InstrumentationBundle $bundle */
        $bundle = self::$kernel->getBundle('InstrumentationBundle');

        // Tearing down a process that emitted no telemetry must not throw ...
        $bundle->__destruct();

        // ... and must not have force-instantiated the unused providers.
        $this->assertFalse($container->initialized(TracerProviderInterface::class));
        $this->assertFalse($container->initialized(MeterProviderInterface::class));
    }

    public function testRecordedSpanIsFlushedOnTeardown(): void
    {
        // The memory exporter appends to this process-global store; isolate from other tests.
        InMemoryStorageManager::spans()->exchangeArray([]);

        self::bootKernel();

        $container = self::$kernel->getContainer();

        // Recording a span through the facade lazily builds the DI tracer provider.
        Tracing::trace('teardown-regression-span')->end();

        $this->assertTrue($container->initialized(TracerProviderInterface::class));

        /** @var InstrumentationBundle $bundle */
        $bundle = self::$kernel->getBundle('InstrumentationBundle');
        $bundle->__destruct();

        $spans = InMemoryStorageManager::spans();
        $this->assertCount(1, $spans);
        $this->assertSame('teardown-regression-span', $spans[0]->getName());
    }
}
