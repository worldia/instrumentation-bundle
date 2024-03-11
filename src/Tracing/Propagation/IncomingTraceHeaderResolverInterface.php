<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Propagation;

use Symfony\Component\HttpFoundation\Request;

interface IncomingTraceHeaderResolverInterface
{
    public function getTraceId(Request $request): string|null;

    public function getSpanId(Request $request): string|null;

    public function isSampled(Request $request): bool|null;
}
