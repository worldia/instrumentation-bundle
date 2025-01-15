<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Semantics\Normalizer;

use Instrumentation\Semantics\Normalizer\ExceptionNormalizer;
use PHPUnit\Framework\TestCase;

class ExceptionNormalizerTest extends TestCase
{
    public function testNormalizesException(): void
    {
        $exception = new \RuntimeException('Some exception');

        $normalized = ExceptionNormalizer::normalizeException($exception);

        $this->assertIsArray($normalized);

        $this->assertArrayHasKey('exception.message', $normalized);
        $this->assertArrayHasKey('exception.type', $normalized);
        $this->assertArrayHasKey('exception.stacktrace', $normalized);

        $this->assertIsString($normalized['exception.message']);
        $this->assertIsString($normalized['exception.type']);
        $this->assertIsString($normalized['exception.stacktrace']);
    }
}
