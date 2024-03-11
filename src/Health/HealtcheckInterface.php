<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Health;

interface HealtcheckInterface
{
    public const HEALTHY = 'healthy';
    public const DEGRADED = 'degraded';
    public const UNHEALTHY = 'unhealthy';

    public function getName(): string;

    public function getDescription(): string|null;

    /**
     * Contextual information about the status.
     * Should be returned when the check is failing to give additional
     * information about the reason for the current status.
     */
    public function getStatusMessage(): string|null;

    /**
     * @return string One of the HealtcheckInterface constants
     */
    public function getStatus(): string;

    public function isCritical(): bool;
}
