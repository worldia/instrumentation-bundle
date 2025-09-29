<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Doctrine\Propagation\DBAL;

use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\ServerVersionProvider;
use Instrumentation\Tracing\Doctrine\Propagation\TraceContextInfoProviderInterface;

final class Driver implements DriverInterface
{
    public function __construct(private DriverInterface $decorated, private TraceContextInfoProviderInterface $infoProvider)
    {
    }

    public function connect(array $params): DriverConnection
    {
        return new Connection($this->decorated->connect($params), $this->infoProvider);
    }

    public function getDatabasePlatform(ServerVersionProvider $versionProvider): AbstractPlatform
    {
        return $this->decorated->getDatabasePlatform($versionProvider);
    }

    public function getExceptionConverter(): ExceptionConverter
    {
        return $this->decorated->getExceptionConverter();
    }
}
