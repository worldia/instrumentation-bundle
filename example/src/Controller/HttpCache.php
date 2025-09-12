<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\Routing\Attribute\Route;

class HttpCache
{
    #[Route('/cache')]
    #[Cache(smaxage: 60)]
    public function httpCache(): Response
    {
        return new Response('Http cache test');
    }
}
