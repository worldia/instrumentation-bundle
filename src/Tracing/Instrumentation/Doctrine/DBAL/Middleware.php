<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Instrumentation\Doctrine\DBAL;

use Doctrine\DBAL\Driver as BaseDriver;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;
use Instrumentation\Semantics\Attribute\DoctrineConnectionAttributeProviderInterface;
use Instrumentation\Tracing\Instrumentation\MainSpanContextInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;

final class Middleware implements MiddlewareInterface
{
    public function __construct(private TracerProviderInterface $tracerProvider, private DoctrineConnectionAttributeProviderInterface $attributeProvider, private MainSpanContextInterface $mainSpanContext, private bool $logQueries = false)
    {
    }

    public function wrap(BaseDriver $driver): BaseDriver
    {
        return new Driver($this->tracerProvider, $this->attributeProvider, $driver, $this->mainSpanContext, $this->logQueries);
    }
}
