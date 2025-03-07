<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Bridge\Sampling\Voter;

use Symfony\Component\Console\Event\ConsoleCommandEvent;

class CommandVoter extends AbstractVoter implements CommandVoterInterface
{
    /**
     * @param ConsoleCommandEvent $event
     */
    protected function getOperationNameFromEvent(object $event): string
    {
        if (!$command = $event->getCommand()) {
            return 'unknown-command';
        }

        return $command->getDefaultName() ?: ($command->getName() ?: 'unknown-command');
    }
}
