<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Doctrine\Instrumentation\DBAL;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\ServerVersionProvider;
use Instrumentation\Semantics\Attribute\DoctrineConnectionAttributeProviderInterface;
use Instrumentation\Tracing\Bridge\MainSpanContextInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;

final class Driver extends AbstractDriverMiddleware
{
    /**
     * @param class-string<DriverConnection> $connectionClass The connection decorator to use, picked at compile
     *                                                        time depending on the installed doctrine/dbal major version
     */
    public function __construct(private TracerProviderInterface $tracerProvider, private DoctrineConnectionAttributeProviderInterface $attributeProvider, DriverInterface $decorated, private MainSpanContextInterface $mainSpanContext, private bool $logQueries, private string $connectionClass = Connection::class)
    {
        parent::__construct($decorated);
    }

    public function connect(array $params): DriverConnection
    {
        $connection = parent::connect($params);
        $attributes = $this->attributeProvider->getAttributes($this->getPlatform($connection), $params);

        $connectionClass = $this->connectionClass;

        return new $connectionClass($this->tracerProvider, $connection, $this->mainSpanContext, $attributes, $this->logQueries);
    }

    private function getPlatform(DriverConnection $connection): AbstractPlatform
    {
        // doctrine/dbal 4: the driver needs a ServerVersionProvider, which the
        // connection itself implements. The interface does not exist on dbal 3,
        // where this instanceof is simply always false.
        if ($connection instanceof ServerVersionProvider) { // @phpstan-ignore-line
            return parent::getDatabasePlatform($connection); // @phpstan-ignore-line
        }

        // doctrine/dbal 3: getDatabasePlatform() takes no argument.
        return parent::getDatabasePlatform(); // @phpstan-ignore-line
    }
}
