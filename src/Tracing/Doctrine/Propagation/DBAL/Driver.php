<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Doctrine\Propagation\DBAL;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Instrumentation\Tracing\Doctrine\Propagation\TraceContextInfoProviderInterface;

final class Driver extends AbstractDriverMiddleware
{
    public function __construct(DriverInterface $decorated, private TraceContextInfoProviderInterface $infoProvider)
    {
        parent::__construct($decorated);
    }

    public function connect(array $params): DriverConnection
    {
        return new Connection(parent::connect($params), $this->infoProvider);
    }
}
