<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\HttpClient\Propagation;

use Instrumentation\Baggage\Propagation\Http\BaggageHeaderProvider;

class PropagationHeadersProvider
{
    /**
     * @return array<string,string>
     */
    public static function getPropagationHeaders(): array
    {
        return array_filter([
            TraceParentHeaderProvider::getHeaderName() => (string) new TraceParentHeaderProvider(),
            TraceStateHeaderProvider::getHeaderName() => (string) new TraceStateHeaderProvider(),
            BaggageHeaderProvider::getHeaderName() => (string) new BaggageHeaderProvider(),
        ]);
    }
}
