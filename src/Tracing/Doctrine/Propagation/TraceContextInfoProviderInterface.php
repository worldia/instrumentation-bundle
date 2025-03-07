<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Doctrine\Propagation;

interface TraceContextInfoProviderInterface
{
    /**
     * @return array<string,string>
     */
    public function getTraceContext(): array;
}
