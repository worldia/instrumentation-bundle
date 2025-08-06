<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class Simple extends AbstractController
{
    #[Route('/{name}', priority: -1000)]
    public function world(string $name): Response
    {
        $href = $this->getTraceLink();

        return new Response(\sprintf('Hello %s!<br><br>Trace: %s', $name, $href));
    }
}
