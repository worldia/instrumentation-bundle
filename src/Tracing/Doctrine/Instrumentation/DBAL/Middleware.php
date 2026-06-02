<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Doctrine\Instrumentation\DBAL;

use Doctrine\DBAL\Driver as BaseDriver;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;
use Instrumentation\Semantics\Attribute\DoctrineConnectionAttributeProviderInterface;
use Instrumentation\Tracing\Bridge\MainSpanContextInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;

final class Middleware implements MiddlewareInterface
{
    /**
     * @param class-string<BaseDriver\Connection> $connectionClass The connection decorator to use, picked at
     *                                                             compile time depending on the installed
     *                                                             doctrine/dbal major version
     */
    public function __construct(private TracerProviderInterface $tracerProvider, private DoctrineConnectionAttributeProviderInterface $attributeProvider, private MainSpanContextInterface $mainSpanContext, private bool $logQueries = false, private string $connectionClass = Connection::class)
    {
    }

    public function wrap(BaseDriver $driver): BaseDriver
    {
        return new Driver($this->tracerProvider, $this->attributeProvider, $driver, $this->mainSpanContext, $this->logQueries, $this->connectionClass);
    }
}
