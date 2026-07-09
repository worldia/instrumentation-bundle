<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation;

use Instrumentation\InstrumentationBundle;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;

final class InstrumentationBundleTest extends TestCase
{
    public function testDestructSwallowsProviderShutdownExceptions(): void
    {
        $container = new Container();

        // __destruct() is duck-typed (it only checks method_exists(..., 'shutdown')), so a bare
        // object whose shutdown() throws is enough to model a provider whose OTLP exporter fails —
        // e.g. the real "Transport factory not defined for protocol: grpc" that killed a build.
        $container->set(TracerProviderInterface::class, new class {
            public function shutdown(): bool
            {
                throw new \RuntimeException('Transport factory not defined for protocol: grpc');
            }
        });

        $bundle = new InstrumentationBundle();
        $bundle->setContainer($container);

        // The provider is built (initialized) and its shutdown will throw ...
        $this->assertTrue($container->initialized(TracerProviderInterface::class));

        // ... but the exception must never escape the destructor.
        $bundle->__destruct();

        $this->addToAssertionCount(1);
    }
}
