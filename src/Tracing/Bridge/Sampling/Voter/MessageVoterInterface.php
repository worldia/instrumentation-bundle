<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Bridge\Sampling\Voter;

use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

interface MessageVoterInterface extends VoterInterface
{
    /**
     * @param WorkerMessageReceivedEvent $event
     */
    public function vote(object $event): string;
}
