<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Sampling\Voter;

use Symfony\Component\Console\Event\ConsoleCommandEvent;

interface CommandVoterInterface extends VoterInterface
{
    /**
     * @param ConsoleCommandEvent $event
     */
    public function vote(object $event): string;
}
