<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics;

use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;

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
        $default = ResourceInfoFactory::defaultResource();
        $attributes = array_merge($default->getAttributes()->toArray(), $this->attributes);

        return ResourceInfo::create(Attributes::create($attributes));
    }
}
