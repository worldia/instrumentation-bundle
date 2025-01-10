<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace App\Messenger;

abstract class Message
{
    public function __construct(
        public readonly string $content,
    ) {
    }
}
