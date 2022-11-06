<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Sampling\Voter;

use Symfony\Component\HttpKernel\Event\RequestEvent;

interface RequestVoterInterface extends VoterInterface
{
    /**
     * @param RequestEvent $event
     */
    public function vote(object $event): string;
}
