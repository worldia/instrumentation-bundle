<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Profiling\Shipping;

/**
 * Sends a finished profile (a pprof) somewhere. Implementations must never make the request wait for the destination's answer:
 * the profile is sent after the response, on the worker that served it.
 */
interface ProfileShipperInterface
{
    /**
     * @param string                $pprof     the encoded profile
     * @param float                 $startedAt when the profiled request started (unix time, seconds)
     * @param array<string, string> $labels    series labels (route, method, ...); the span id is already a label of every sample
     */
    public function ship(string $pprof, float $startedAt, array $labels): void;

    /** Finishes what a previous call left pending (an answer still to read). Cheap when there is none; called on every request. */
    public function reap(): void;
}
