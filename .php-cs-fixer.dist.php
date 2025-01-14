<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

require_once 'vendor/autoload.php';

return CodingStandards\Factory::createPhpCsFixerConfig(__DIR__, [
    'rules' => [
        'nullable_type_declaration' => ['syntax' => 'union'],
    ],
]);
