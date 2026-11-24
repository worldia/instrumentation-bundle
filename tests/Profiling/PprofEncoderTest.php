<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Profiling;

use Instrumentation\Profiling\Encoding\PprofEncoder;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

#[RequiresPhpExtension('excimer')]
final class PprofEncoderTest extends TestCase
{
    public function testTheProfileCarriesTheSpanLabelTheFramesAndTheSampleType(): void
    {
        $profiler = new \ExcimerProfiler();
        $profiler->setPeriod(0.002);
        $profiler->setEventType(\EXCIMER_REAL);
        $profiler->start();
        self::spin();
        $profiler->stop();

        $pprof = (new PprofEncoder())->encode($profiler->getLog(), 'wall', 0.002, 'abcdef0123456789', 1_700_000_000_000_000_000, 50_000_000);

        // Field 6 of Profile is the string table: every string the profile names is in it, once.
        $strings = self::stringTable($pprof);
        $this->assertSame('', $strings[0], 'the pprof string table starts with the empty string');
        $this->assertContains('wall', $strings);
        $this->assertContains('nanoseconds', $strings);
        $this->assertContains('span_id', $strings);
        $this->assertContains('abcdef0123456789', $strings);
        $this->assertContains(self::class.'::spin', $strings);
        $this->assertSame(array_unique($strings), $strings, 'no string is repeated');
    }

    public function testNoSpanIdMeansNoSampleLabel(): void
    {
        $profiler = new \ExcimerProfiler();
        $profiler->setPeriod(0.002);
        $profiler->start();
        self::spin();
        $profiler->stop();

        $strings = self::stringTable((new PprofEncoder())->encode($profiler->getLog(), 'cpu', 0.002, '', 1, 1));

        $this->assertNotContains('span_id', $strings);
        $this->assertContains('cpu', $strings);
    }

    private static function spin(): void
    {
        $end = hrtime(true) + 60_000_000;
        while (hrtime(true) < $end) {
            md5(random_bytes(8));
        }
    }

    /** @return list<string> the entries of the Profile message's string table (field 6) */
    private static function stringTable(string $message): array
    {
        $strings = [];
        $i = 0;
        $length = \strlen($message);
        while ($i < $length) {
            $tag = self::varint($message, $i);
            $field = $tag >> 3;
            if (0 === ($tag & 7)) {
                self::varint($message, $i);
                continue;
            }
            self::assertSame(2, $tag & 7, 'only varint and length-delimited fields are used');
            $size = self::varint($message, $i);
            if (6 === $field) {
                $strings[] = substr($message, $i, $size);
            }
            $i += $size;
        }

        return $strings;
    }

    private static function varint(string $bytes, int &$offset): int
    {
        $value = 0;
        $shift = 0;
        do {
            $byte = \ord($bytes[$offset++]);
            $value |= ($byte & 0x7F) << $shift;
            $shift += 7;
        } while ($byte & 0x80);

        return $value;
    }
}
