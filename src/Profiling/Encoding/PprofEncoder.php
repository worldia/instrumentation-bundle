<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Profiling\Encoding;

/**
 * Encodes an Excimer log as a pprof profile (the protobuf Pyroscope and Grafana speak), by hand: the message is five
 * small structures and the protobuf runtime would be a dependency for a few varints.
 *
 * What the folded text format cannot carry and this does: every sample has the label `span_id`, which Grafana's span
 * profile query (SelectMergeSpanProfile, "profiles for this span" on a trace) filters on, and every frame has its file
 * and line. One sample type, `wall` or `cpu` in nanoseconds: an Excimer event is one period of that clock.
 */
final class PprofEncoder
{
    /** @var list<string> */
    private array $strings = [''];

    /** @var array<string, int> */
    private array $stringIds = ['' => 0];

    /** @var array<string, int> function key => id (1-based) */
    private array $functions = [];

    /** @var array<string, int> location key => id (1-based) */
    private array $locations = [];

    /** @var list<string> encoded Function messages */
    private array $functionMessages = [];

    /** @var list<string> encoded Location messages */
    private array $locationMessages = [];

    /**
     * @param string $type   sample type name: `wall` or `cpu`
     * @param float  $period seconds per sampling period
     * @param string $spanId the `span_id` sample label, empty for none
     */
    public function encode(\ExcimerLog $log, string $type, float $period, string $spanId, int $startNanos, int $durationNanos): string
    {
        $this->clear();

        /** @var array<string, array{0: list<int>, 1: int}> $stacks */
        $stacks = [];
        foreach ($log as $entry) {
            $ids = [];
            /** @var array<string, mixed> $frame */
            foreach ($entry->getTrace() as $frame) { // leaf first, as pprof wants it
                $ids[] = $this->location($frame);
            }
            if ([] === $ids) {
                continue;
            }
            $key = implode(',', $ids);
            $stacks[$key] ??= [$ids, 0];
            $stacks[$key][1] += $entry->getEventCount();
        }

        $periodNanos = (int) round($period * 1e9);
        $wallType = $this->string($type);
        $unit = $this->string('nanoseconds');
        $spanKey = '' === $spanId ? 0 : $this->string('span_id');
        $spanValue = '' === $spanId ? 0 : $this->string($spanId);

        $out = $this->message(1, $this->varint(1, $wallType).$this->varint(2, $unit)); // sample_type
        foreach ($stacks as [$ids, $events]) {
            $sample = $this->packed(1, $ids).$this->packed(2, [$events * $periodNanos]);
            if ('' !== $spanId) {
                $sample .= $this->message(3, $this->varint(1, $spanKey).$this->varint(2, $spanValue)); // label
            }
            $out .= $this->message(2, $sample);
        }
        foreach ($this->locationMessages as $message) {
            $out .= $this->message(4, $message);
        }
        foreach ($this->functionMessages as $message) {
            $out .= $this->message(5, $message);
        }
        foreach ($this->strings as $string) {
            $out .= $this->message(6, $string);
        }
        $out .= $this->varint(9, $startNanos).$this->varint(10, $durationNanos);
        $out .= $this->message(11, $this->varint(1, $wallType).$this->varint(2, $unit)); // period_type
        $out .= $this->varint(12, $periodNanos);

        return $out;
    }

    private function clear(): void
    {
        $this->strings = [''];
        $this->stringIds = ['' => 0];
        $this->functions = [];
        $this->locations = [];
        $this->functionMessages = [];
        $this->locationMessages = [];
    }

    /** @param array<string, mixed> $frame one element of ExcimerLogEntry::getTrace() */
    private function location(array $frame): int
    {
        $class = \is_string($frame['class'] ?? null) ? $frame['class'].'::' : '';
        $function = $frame['function'] ?? null;
        $name = \is_string($function) ? $class.$function : (isset($frame['closure_line']) ? '{closure}' : '{main}');
        $file = \is_string($frame['file'] ?? null) ? $frame['file'] : '';
        $line = \is_int($frame['line'] ?? null) ? $frame['line'] : 0;

        $fnKey = $name."\0".$file;
        if (!isset($this->functions[$fnKey])) {
            $this->functions[$fnKey] = \count($this->functions) + 1;
            $this->functionMessages[] = $this->varint(1, $this->functions[$fnKey])
                .$this->varint(2, $this->string($name))
                .$this->varint(3, $this->string($name))
                .$this->varint(4, $this->string($file));
        }
        $locKey = $fnKey."\0".$line;
        if (!isset($this->locations[$locKey])) {
            $this->locations[$locKey] = \count($this->locations) + 1;
            $this->locationMessages[] = $this->varint(1, $this->locations[$locKey])
                .$this->message(4, $this->varint(1, $this->functions[$fnKey]).$this->varint(2, $line)); // Line
        }

        return $this->locations[$locKey];
    }

    private function string(string $value): int
    {
        return $this->stringIds[$value] ??= (function () use ($value): int {
            $this->strings[] = $value;

            return \count($this->strings) - 1;
        })();
    }

    private function varint(int $field, int $value): string
    {
        return $this->rawVarint($field << 3).$this->rawVarint($value);
    }

    private function message(int $field, string $payload): string
    {
        return $this->rawVarint(($field << 3) | 2).$this->rawVarint(\strlen($payload)).$payload;
    }

    /** @param list<int> $values */
    private function packed(int $field, array $values): string
    {
        return $this->message($field, implode('', array_map($this->rawVarint(...), $values)));
    }

    private function rawVarint(int $value): string
    {
        $out = '';
        while ($value > 0x7F) {
            $out .= \chr(($value & 0x7F) | 0x80);
            $value >>= 7;
        }

        return $out.\chr($value & 0x7F);
    }
}
