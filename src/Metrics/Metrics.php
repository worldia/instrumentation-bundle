<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Metrics;

final class Metrics
{
    private static ?RegistryInterface $registry = null;

    public static function getRegistry(): RegistryInterface
    {
        if (!self::$registry) {
            throw new \RuntimeException('Registry was not set.');
        }

        return self::$registry;
    }

    public static function setRegistry(RegistryInterface $registry): void
    {
        self::$registry = $registry;
    }
}
