<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics;

use OpenTelemetry\SDK\Resource\ResourceInfo;

interface ResourceInfoProviderInterface
{
    public function getInfo(): ResourceInfo;
}
