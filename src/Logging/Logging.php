<?php

declare(strict_types=1);

/*
 * This file is part of the platform/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Logging;

use OpenTelemetry\SDK\GlobalLoggerHolder;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Logging
{
    private static ?LoggerInterface $logger = null;

    public function __construct(?LoggerInterface $logger)
    {
        if (self::$logger) {
            return;
        }

        self::$logger = $logger ?: new NullLogger();
        GlobalLoggerHolder::set(self::$logger);
    }

    public static function getLogger(): LoggerInterface
    {
        if (!self::$logger) {
            throw new \LogicException('Logging not initialized.');
        }

        return self::$logger;
    }
}
