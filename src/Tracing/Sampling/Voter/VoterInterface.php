<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Sampling\Voter;

use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

interface VoterInterface
{
    public const VOTE_ABSTAIN = 'abstain';
    public const VOTE_RECORD = 'record';
    public const VOTE_DROP = 'drop';

    /**
     * @param RequestEvent|ConsoleCommandEvent|WorkerMessageReceivedEvent $event
     *
     * @return self::VOTE_*
     */
    public function vote(object $event): string;
}
