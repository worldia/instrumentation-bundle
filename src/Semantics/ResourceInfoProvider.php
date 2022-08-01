<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics;

use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;

class ResourceInfoProvider implements ResourceInfoProviderInterface
{
    /**
     * @param array<string,string> $attributes
     */
    public function __construct(private array $attributes)
    {
    }

    public function getInfo(): ResourceInfo
    {
        $default = ResourceInfo::defaultResource();
        $attributes = array_merge($default->getAttributes()->toArray(), $this->attributes);

        return ResourceInfo::create(new Attributes($attributes));
    }
}
