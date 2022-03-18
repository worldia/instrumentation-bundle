<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Metrics;

interface MetricProviderInterface
{
    /**
     * @return array<string,array{
     *           type:string,
     *           help:string,
     *           labels?:array<string>,
     *           buckets?:array<int|float>|null
     *         }>
     */
    public static function getProvidedMetrics(): array;
}
