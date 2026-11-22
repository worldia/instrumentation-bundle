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
 * One tally serves every connection of the container: a request spanning several stores has one total. It follows the
 * main span, so a connection that outlives a request in a long-lived worker starts a new count for the next one.
 */
final class QueryTally
{
    private string|null $mainSpanId = null;
    private int $count = 0;
    private int $totalNs = 0;
    private int $spans = 0;

    private readonly int $minDurationNs;

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
}
