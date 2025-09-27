<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Doctrine\Instrumentation\DBAL;

use Doctrine\DBAL\Connection\StaticServerVersionProvider;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\ServerVersionProvider;
use Instrumentation\Semantics\Attribute\DoctrineConnectionAttributeProviderInterface;
use Instrumentation\Tracing\Bridge\MainSpanContextInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;

final class Driver implements DriverInterface
{
    public function __construct(private TracerProviderInterface $tracerProvider, private DoctrineConnectionAttributeProviderInterface $attributeProvider, private DriverInterface $decorated, private MainSpanContextInterface $mainSpanContext, private bool $logQueries)
    {
    }

    public function connect(array $params): DriverConnection
    {
        $versionProvider = new StaticServerVersionProvider('');
        if (isset($params['serverVersion'])) {
            $versionProvider = new StaticServerVersionProvider($params['serverVersion']);
        } elseif (isset($params['primary']['serverVersion'])) {
            $versionProvider = new StaticServerVersionProvider($params['primary']['serverVersion']);
        }

        $attributes = $this->attributeProvider->getAttributes(
            $this->decorated->getDatabasePlatform($versionProvider),
            $params,
        );

        return new Connection($this->tracerProvider, $this->decorated->connect($params), $this->mainSpanContext, $attributes, $this->logQueries);
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
