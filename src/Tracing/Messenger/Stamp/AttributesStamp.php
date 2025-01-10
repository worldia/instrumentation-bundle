<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Messenger\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

final class AttributesStamp implements StampInterface
{
    /**
     * @param array<non-empty-string,non-empty-string> $attributes
     */
    public function __construct(private array $attributes = [])
    {
    }

    /**
     * @return array<non-empty-string,non-empty-string>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
