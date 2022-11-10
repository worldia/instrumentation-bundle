<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Propagation\Doctrine\DBAL;

use Doctrine\DBAL\Driver as BaseDriver;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;
use Instrumentation\Tracing\Propagation\Doctrine\TraceContextInfoProviderInterface;

final class Middleware implements MiddlewareInterface
{
    public function __construct(private TraceContextInfoProviderInterface $infoProvider)
    {
    }

    public function wrap(BaseDriver $driver): BaseDriver
    {
        return new Driver($driver, $this->infoProvider);
    }
}
