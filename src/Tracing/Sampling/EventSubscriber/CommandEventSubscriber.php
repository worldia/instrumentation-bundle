<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Sampling\EventSubscriber;

use Symfony\Component\Console\Event\ConsoleCommandEvent;

class CommandEventSubscriber extends AbstractEventSubscriber
{
    protected static function getEventClass(): string
    {
        return ConsoleCommandEvent::class;
    }
}
