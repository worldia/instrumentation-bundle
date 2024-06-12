<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Metrics;

trait IterableAttributesTrait
{
    /**
     * @param iterable<string,array<string,mixed>> $attributes
     *
     * @return array<string,array<string,mixed>>
     */
    public function normalizeAttributes(iterable $attributes): array
    {
        $newAttr = [];
        foreach ($attributes as $key => $value) {
            $newAttr[$key] = $value;
        }

        return $newAttr;
    }
}
