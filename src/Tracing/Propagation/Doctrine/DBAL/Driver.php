<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Propagation\Doctrine\DBAL;

use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\VersionAwarePlatformDriver;
use Instrumentation\Tracing\Propagation\Doctrine\TraceContextInfoProviderInterface;

final class Driver implements VersionAwarePlatformDriver
{
    public function __construct(private DriverInterface $decorated, private TraceContextInfoProviderInterface $infoProvider)
    {
    }

    public function connect(array $params): DriverConnection
    {
        return new Connection($this->decorated->connect($params), $this->infoProvider);
    }

    public function getDatabasePlatform(): AbstractPlatform
    {
        return $this->decorated->getDatabasePlatform();
    }

    /**
     * @phpstan-template T of AbstractPlatform
     *
     * @phpstan-param T $platform
     *
     * @phpstan-return AbstractSchemaManager<T>
     */
    public function getSchemaManager(DBALConnection $conn, AbstractPlatform $platform): AbstractSchemaManager
    {
        return $this->decorated->getSchemaManager($conn, $platform);
    }

    public function getExceptionConverter(): ExceptionConverter
    {
        return $this->decorated->getExceptionConverter();
    }

    public function createDatabasePlatformForVersion($version)
    {
        if ($this->decorated instanceof VersionAwarePlatformDriver) {
            return $this->decorated->createDatabasePlatformForVersion($version);
        }

        return $this->decorated->getDatabasePlatform();
    }
}
