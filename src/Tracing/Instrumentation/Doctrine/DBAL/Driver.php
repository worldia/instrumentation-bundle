<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Instrumentation\Doctrine\DBAL;

use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\VersionAwarePlatformDriver;
use Instrumentation\Semantics\Attribute\DoctrineConnectionAttributeProviderInterface;
use Instrumentation\Tracing\Instrumentation\MainSpanContext;
use OpenTelemetry\API\Trace\TracerProviderInterface;

final class Driver implements VersionAwarePlatformDriver
{
    public function __construct(private TracerProviderInterface $tracerProvider, private DoctrineConnectionAttributeProviderInterface $attributeProvider, private DriverInterface $decorated, private MainSpanContext $mainSpanContext)
    {
    }

    public function connect(array $params): DriverConnection
    {
        $attributes = $this->attributeProvider->getAttributes($this->decorated->getDatabasePlatform(), $params);

        return new Connection($this->tracerProvider, $this->decorated->connect($params), $this->mainSpanContext, $attributes);
    }

    public function getDatabasePlatform(): AbstractPlatform
    {
        return $this->decorated->getDatabasePlatform();
    }

    /**
     * @phpstan-template T of AbstractPlatform
     * @phpstan-param T $platform
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
