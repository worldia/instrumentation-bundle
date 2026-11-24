<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Profiling\Stub;

use Instrumentation\Profiling\Shipping\ProfileShipperInterface;

final class RecordingShipper implements ProfileShipperInterface
{
    /** @var list<array{pprof: string, startedAt: float, labels: array<string, string>}> */
    public array $shipped = [];

    public int $reaped = 0;

    public function ship(string $pprof, float $startedAt, array $labels): void
    {
        $this->shipped[] = ['pprof' => $pprof, 'startedAt' => $startedAt, 'labels' => $labels];
    }

    public function reap(): void
    {
        ++$this->reaped;
    }
}
