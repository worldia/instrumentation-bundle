<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Sampling\EventSubscriber;

use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

class MessageEventSubscriber extends AbstractEventSubscriber
{
    protected static function getEventClass(): string
    {
        return WorkerMessageReceivedEvent::class;
    }
}
