<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class Error
{
    #[Route('/error')]
    public function throw500(): void
    {
        throw new \RuntimeException('Something bad happened');
    }

    #[Route('/random-error')]
    public function throwRandomly(): Response
    {
        if (rand(0, 1) > .5) {
            throw new \RuntimeException('Something bad happened');
        }

        return new Response('OK');
    }
}
