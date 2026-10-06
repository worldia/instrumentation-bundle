<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Functional;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SDK\Common\Export\InMemoryStorageManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;

/**
 * Long-running workers (FrankenPHP, RoadRunner) serve many requests per process, so the batch span
 * processor would otherwise hold a request's spans until the batch fills or the process exits. The
 * request's spans must be exported when the kernel terminates, not at teardown.
 */
final class RequestFlushTest extends KernelTestCase
{
    protected function setUp(): void
    {
        (new Filesystem())->remove(sys_get_temp_dir().'/instrumentation-bundle-tests');
    }

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testRequestSpansAreExportedWhenTheKernelTerminates(): void
    {
        // The memory exporter appends to this process-global store; isolate from other tests.
        InMemoryStorageManager::spans()->exchangeArray([]);

        $kernel = self::bootKernel();

        $request = Request::create('/request-flush');
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        // No InstrumentationBundle::__destruct() / provider shutdown here: the export must already
        // have happened at TERMINATE.
        $serverSpans = array_values(array_filter(
            InMemoryStorageManager::spans()->getArrayCopy(),
            static fn ($span): bool => SpanKind::KIND_SERVER === $span->getKind(),
        ));

        $this->assertCount(1, $serverSpans);
        $this->assertSame('http.error 404', $serverSpans[0]->getName());
    }
}
