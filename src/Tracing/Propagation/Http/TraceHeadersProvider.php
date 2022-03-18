<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Propagation\Http;

class TraceHeadersProvider
{
    /**
     * @return array<string,string>
     */
    public static function getHeaders(): array
    {
        return array_filter([
            TraceParentHeaderProvider::getHeaderName() => (string) new TraceParentHeaderProvider(),
            TraceStateHeaderProvider::getHeaderName() => (string) new TraceStateHeaderProvider(),
        ]);
    }
}
