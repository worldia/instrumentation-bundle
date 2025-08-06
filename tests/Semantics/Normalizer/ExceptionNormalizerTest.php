<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Semantics\Normalizer;

use Instrumentation\Semantics\Normalizer\ExceptionNormalizer;
use OpenTelemetry\SemConv\TraceAttributes;
use PHPUnit\Framework\TestCase;

class ExceptionNormalizerTest extends TestCase
{
    public function testNormalizesException(): void
    {
        $exception = new FixedStackTraceException(
            'Something went wrong',
            'Stack trace #10',
        );

        $normalized = ExceptionNormalizer::normalizeException($exception);

        $this->assertArraysEqualIgnoringKeyOrder([
            TraceAttributes::EXCEPTION_MESSAGE => 'Something went wrong',
            TraceAttributes::EXCEPTION_TYPE => $exception::class,
            TraceAttributes::EXCEPTION_STACKTRACE => <<<'EOL'
            Test\Exception: Something went wrong
            Stack trace #10
            EOL,
        ], $normalized);
    }

    public function testNormalizedExceptionContainsPreviousExceptionInformation(): void
    {
        $exception = new FixedStackTraceException(
            'Something went wrong',
            'Stack trace #10',
            new FixedStackTraceException('Root cause', 'Stack trace #20'),
        );

        $normalized = ExceptionNormalizer::normalizeException($exception);

        $this->assertArraysEqualIgnoringKeyOrder([
            TraceAttributes::EXCEPTION_MESSAGE => 'Something went wrong',
            TraceAttributes::EXCEPTION_TYPE => $exception::class,
            TraceAttributes::EXCEPTION_STACKTRACE => <<<'EOL'
            Test\Exception: Root cause
            Stack trace #20
            Next Test\Exception: Something went wrong
            Stack trace #10
            EOL,
        ], $normalized);
    }

    /**
     * @param array<string, string> $expected
     * @param array<string, string> $actual
     */
    private function assertArraysEqualIgnoringKeyOrder(array $expected, array $actual): void
    {
        ksort($expected);
        ksort($actual);
        $this->assertEquals($expected, $actual);
    }
}

final class FixedStackTraceException extends \Exception
{
    public function __construct(
        string $message,
        private string $stackTrace,
        private self|null $previous = null,
    ) {
        parent::__construct($message, 0);
    }

    public function __toString()
    {
        return $this->previous?->format().$this->format();
    }

    private function format(): string
    {
        $message = 'Test\Exception: '.$this->message.\PHP_EOL.$this->stackTrace;
        if ($this->previous) {
            $message = \PHP_EOL.'Next '.$message;
        }

        return $message;
    }
}
