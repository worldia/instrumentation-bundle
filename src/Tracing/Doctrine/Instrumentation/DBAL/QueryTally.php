<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Doctrine\Instrumentation\DBAL;

use Instrumentation\Tracing\Bridge\MainSpanContextInterface;

/**
 * What the statements of one request, command or message add up to, and which of them are worth a span of their own.
 *
 * A span per statement is unbounded: a request runs hundreds of statements and a bulk handler thousands, three spans
 * each, which overruns a trace backend. With a minimum duration (`tracing.doctrine.min_duration_ms`) only the
 * statements at least that slow are spans; every statement is still counted and timed onto the main span
 * (`db.statements.count`, `db.statements.total_ms`, `db.statements.spans`), so an N+1 shows on its request even though
 * its fast statements are not in the trace. Without one (0, the default) every statement is a span, as before.
 *
 * A request that is being profiled (see the profiling subscriber) also has its statements captured: each one, in order, is
 * an event of the main span (`db.statement`: its text, its duration, its database), up to {@see self::CAPTURE_MAX}, so a trace
 * of that request says which statements it ran and which cost the most, with no span per statement. Quoted literals are masked in
 * every statement (`'...'`, `E'...'`, `$$...$$`, an unterminated one included), and the numbers of a statement run as it is (a
 * prepared statement keeps its numbers: its placeholders are `$1` or `?`, and a `LIMIT 10` is no secret). It is the SQL the
 * application wrote, so it is for the one request an operator asked for, not for traffic.
 *
 * One tally serves every connection of the container: a request spanning several stores has one total. It follows the
 * main span, so a connection that outlives a request in a long-lived worker starts a new count for the next one.
 */
final class QueryTally
{
    private string|null $mainSpanId = null;
    private int $count = 0;
    private int $totalNs = 0;
    private int $spans = 0;

    /** The most statements captured on a main span: under the OpenTelemetry SDK's default limit of 128 events per span. */
    public const CAPTURE_MAX = 100;

    private const CAPTURE_TEXT_MAX = 400;

    private readonly int $minDurationNs;

    private bool $capturing = false;
    private string|null $captureSpanId = null;
    private int $captured = 0;
    private int $captureDropped = 0;

    /**
     * @param int $minDurationMs the shortest statement, in milliseconds, that is a span of its own; 0: every statement is
     */
    public function __construct(
        private readonly MainSpanContextInterface $mainSpanContext,
        int $minDurationMs = 0,
    ) {
        $this->minDurationNs = max(0, $minDurationMs) * 1_000_000;
    }

    /** Whether statements below a minimum duration are left out of the trace. */
    public function bounded(): bool
    {
        return $this->minDurationNs > 0;
    }

    /** Whether a statement that took this long is a span of its own. */
    public function worthASpan(int $durationNs): bool
    {
        return $durationNs >= $this->minDurationNs;
    }

    /**
     * Count and time a statement on the main span; `$spanned` says it became a span of its own.
     */
    public function record(int $durationNs, bool $spanned): void
    {
        $main = $this->mainSpanContext->getMainSpan();
        $id = $main->getContext()->getSpanId();
        if ($id !== $this->mainSpanId) {
            $this->mainSpanId = $id;
            $this->count = 0;
            $this->totalNs = 0;
            $this->spans = 0;
        }

        ++$this->count;
        $this->totalNs += $durationNs;
        if ($spanned) {
            ++$this->spans;
        }

        if ($main->isRecording()) {
            $main->setAttributes([
                'db.statements.count' => $this->count,
                'db.statements.total_ms' => round($this->totalNs / 1e6, 1),
                'db.statements.spans' => $this->spans,
            ]);
        }
    }

    /** Capture the statements of the request being handled (on) or stop (off). */
    public function capture(bool $on): void
    {
        $this->capturing = $on;
        $this->captureSpanId = null;
        $this->captured = $this->captureDropped = 0;
    }

    public function capturing(): bool
    {
        return $this->capturing;
    }

    /**
     * A statement that ran, as an event of the main span.
     *
     * @param array<string, mixed> $attributes the connection's attributes (`db.namespace`, ...)
     * @param bool                 $prepared   whether `$sql` is a prepared statement, whose values are bound and not in it
     */
    public function captureStatement(string $sql, array $attributes, int $durationNs, bool $prepared): void
    {
        if (!$this->capturing) {
            return;
        }
        // It runs in the `finally` of a statement: whatever it does, it never replaces the error of the statement.
        try {
            $main = $this->mainSpanContext->getMainSpan();
            if (!$main->isRecording()) {
                return;
            }
            $id = $main->getContext()->getSpanId();
            if ($id !== $this->captureSpanId) {
                $this->captureSpanId = $id;
                $this->captured = $this->captureDropped = 0;
            }
            if ($this->captured >= self::CAPTURE_MAX) {
                ++$this->captureDropped;
                $main->setAttribute('db.statements.capture_dropped', $this->captureDropped);

                return;
            }

            $database = $attributes['db.namespace'] ?? '';
            $main->addEvent('db.statement', [
                'db.statement.index' => ++$this->captured,
                'db.statement.text' => self::text($sql, $prepared),
                'db.statement.duration_ms' => round($durationNs / 1e6, 2),
                'db.namespace' => \is_string($database) ? $database : '',
            ]);
        } catch (\Throwable) {
            // a statement left out of the capture
        }
    }

    /** Statements longer than this are not read for literals: their text is not kept. */
    private const CAPTURE_PARSE_MAX = 65536;

    private static function text(string $sql, bool $prepared): string
    {
        if (\strlen($sql) > self::CAPTURE_PARSE_MAX) {
            return \sprintf('[a statement of %d bytes]', \strlen($sql));
        }
        // The quoted literals first, before anything else is cut or collapsed: a string with a quote escaped by a backslash, an
        // `E'...'` one, a dollar-quoted one, and a quote that is never closed (all that follows it is the literal).
        $sql = (string) preg_replace(
            ["~'(?:[^'\\\\]|\\\\.|'')*(?:'|$)~s", '~\$([A-Za-z_]*)\$.*?(?:\$\1\$|$)~s'],
            ["'?'", "'?'"],
            $sql,
        );
        if (!$prepared) {
            $sql = (string) preg_replace('~\b\d+(?:\.\d+)?\b~', '?', $sql);
        }
        // The comment the trace propagation adds is no part of the statement.
        $sql = trim((string) preg_replace(['~/\*.*?(?:\*/|$)~s', '~\s+~'], ['', ' '], $sql));

        return mb_strimwidth($sql, 0, self::CAPTURE_TEXT_MAX, '...');
    }
}
