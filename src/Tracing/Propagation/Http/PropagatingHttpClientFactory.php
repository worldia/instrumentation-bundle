<?php

declare(strict_types=1);

/*
 * This file is part of the platform/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Propagation\Http;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PropagatingHttpClientFactory
{
    public static function getClient(): HttpClientInterface
    {
        return HttpClient::create(['headers' => TraceHeadersProvider::getHeaders()]);
    }
}
