<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Sampling\Voter;

use Symfony\Component\HttpKernel\Event\RequestEvent;

class RequestVoter extends AbstractVoter implements RequestVoterInterface
{
    /**
     * @param array<string> $operationsBlacklist
     * @param array<string> $methodsWhitelist
     */
    public function __construct(array $operationsBlacklist, private array $methodsWhitelist)
    {
        parent::__construct($operationsBlacklist);
    }

    /**
     * @param RequestEvent $event
     */
    public function vote(object $event): string
    {
        if (!$event->isMainRequest()) {
            return VoterInterface::VOTE_ABSTAIN;
        }

        if (!\in_array($event->getRequest()->getMethod(), $this->methodsWhitelist)) {
            return VoterInterface::VOTE_DROP;
        }

        if (null !== $force = $event->getRequest()->headers->get('x-trace')) {
            return (bool) $force ? VoterInterface::VOTE_RECORD : VoterInterface::VOTE_DROP;
        }

        return parent::vote($event);
    }

    /**
     * @param RequestEvent $event
     */
    protected function getOperationNameFromEvent(object $event): string
    {
        return $event->getRequest()->getPathInfo();
    }
}
