<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Http;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PropagatingHttpClientFactory
{
    public static function getClient(): HttpClientInterface
    {
        return HttpClient::create(['headers' => PropagationHeadersProvider::getPropagationHeaders()]);
    }
}
