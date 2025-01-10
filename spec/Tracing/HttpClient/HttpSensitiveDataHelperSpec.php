<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace spec\Instrumentation\Tracing\HttpClient;

use PhpSpec\ObjectBehavior;

class HttpSensitiveDataHelperSpec extends ObjectBehavior
{
    public function it_removes_credentials_from_url(): void
    {
        $this::filterUrl('https://root:p4ssw0rd@example.com?foo=bar#baz')->shouldReturn('https://example.com?foo=bar#baz');
    }

    public function it_removes_credentials_from_headers(): void
    {
        $this::filterHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer kjfdhsfkjshgskjq',
            'proxy-authorization' => 'Basic gperfbshkdbfzdzl',
        ])->shouldReturn([
            'Content-Type' => 'application/json',
        ]);
    }
}
