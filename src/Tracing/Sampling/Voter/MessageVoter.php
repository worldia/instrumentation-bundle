<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Sampling\Voter;

use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

class MessageVoter extends AbstractVoter implements MessageVoterInterface
{
    /**
     * @param WorkerMessageReceivedEvent $event
     */
    protected function getOperationNameFromEvent(object $event): string
    {
        return \get_class($event->getEnvelope()->getMessage());
    }
}
