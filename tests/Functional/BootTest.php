<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Functional;

use Instrumentation\Metrics\AI\Platform\MeteringPlatform;
use Instrumentation\Tracing\AI\Platform\TracingPlatform;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Tests\Instrumentation\Functional\Stub\DummyPlatform;

/**
 * Confirms the bundle boots inside a real Symfony kernel (container compiles,
 * services resolve, AI platform decoration is applied). Runs against whichever
 * Symfony major is installed, so it validates both the 7.4 and 8.x legs.
 */
final class BootTest extends KernelTestCase
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

    public function testKernelBootsAndCompilesContainer(): void
    {
        self::bootKernel();

        $container = self::getContainer();

        $this->assertInstanceOf(TracerProviderInterface::class, $container->get(TracerProviderInterface::class));
        $this->assertInstanceOf(MeterProviderInterface::class, $container->get(MeterProviderInterface::class));
    }

    public function testAiPlatformServiceIsDecorated(): void
    {
        self::bootKernel();

        $platform = self::getContainer()->get('app.platform.openai');

        $this->assertInstanceOf(PlatformInterface::class, $platform);
        $this->assertNotInstanceOf(DummyPlatform::class, $platform);
        $this->assertTrue(
            $platform instanceof TracingPlatform || $platform instanceof MeteringPlatform,
            'The ai.platform service should be decorated by the bundle AI platform decorators.',
        );
    }

    /**
     * Booting + compiling + instantiating the bundle's services must not trigger
     * any deprecation originating from this package's own source (PHP or Symfony,
     * including @-silenced ones). Deprecations from third-party vendors are ignored.
     */
    public function testBootTriggersNoDeprecationsFromThisPackage(): void
    {
        $srcDir = realpath(\dirname(__DIR__, 2).'/src');
        $deprecations = [];

        $previous = set_error_handler(
            static function (int $type, string $message, string $file = '', int $line = 0) use (&$deprecations, &$previous, $srcDir) {
                if (\E_USER_DEPRECATED === $type || \E_DEPRECATED === $type) {
                    foreach (debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
                        if (isset($frame['file']) && str_starts_with($frame['file'], $srcDir)) {
                            $deprecations[] = \sprintf('%s (via %s:%d)', $message, $frame['file'], $frame['line'] ?? 0);
                            break;
                        }
                    }

                    return true;
                }

                return null !== $previous && ($previous)($type, $message, $file, $line);
            }
        );

        try {
            self::bootKernel();
            $container = self::getContainer();
            $container->get(TracerProviderInterface::class);
            $container->get(MeterProviderInterface::class);
            $container->get('app.platform.openai');
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $deprecations, \sprintf(
            "Booting the bundle triggered %d deprecation(s) from its own code:\n- %s",
            \count($deprecations),
            implode("\n- ", $deprecations),
        ));
    }
}
