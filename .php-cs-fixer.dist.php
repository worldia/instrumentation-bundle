<?php

require_once 'vendor/autoload.php';

return CodingStandards\Factory::createPhpCsFixerConfig(__DIR__, [
    'rules' => [
        'nullable_type_declaration' => ['syntax' => 'union']
    ]
]);
