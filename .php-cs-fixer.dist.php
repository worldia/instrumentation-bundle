<?php

declare(strict_types=1);

/*
 * This file is part of the platform/client-common package.
 * (c) Worldia <developers@worldia.com>
 */

try {
    require_once 'vendor/autoload.php';
} catch (\Throwable) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

return CodingStandards\Factory::createPhpCsFixerConfig(__DIR__);
