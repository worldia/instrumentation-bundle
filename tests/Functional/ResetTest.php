<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Functional;

use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\SDK\Common\Export\InMemoryStorageManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * `services_resetter` runs between two requests of a worker, after each Messenger message and when a
 * test kernel shuts down: it must flush the spans held by the batch span processor.
 */
final class ResetTest extends KernelTestCase
{
    protected function setUp(): void
    {
        (new Filesystem())->remove(sys_get_temp_dir().'/instrumentation-bundle-tests');
    }

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testServicesResetterFlushesTheTracerProvider(): void
    {
        // The memory exporter appends to this process-global store; isolate from other tests.
        InMemoryStorageManager::spans()->exchangeArray([]);

        self::bootKernel();
        $container = self::getContainer();

        $container->get(TracerProviderInterface::class)->getTracer('test')->spanBuilder('reset-me')->startSpan()->end();

        // Batched, not exported yet.
        $this->assertSame([], $this->exportedSpanNames());

        $container->get('services_resetter')->reset();

        $this->assertSame(['reset-me'], $this->exportedSpanNames());
    }

    /**
     * @return list<string>
     */
    private function exportedSpanNames(): array
    {
        return array_map(
            static fn ($span): string => $span->getName(),
            array_values(InMemoryStorageManager::spans()->getArrayCopy()),
        );
    }
}
