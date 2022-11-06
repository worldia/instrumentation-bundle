<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Sampling\Voter;

abstract class AbstractVoter implements VoterInterface
{
    /**
     * @param array<string> $blacklist
     */
    public function __construct(private array $blacklist)
    {
    }

    public function vote(object $event): string
    {
        $operation = $this->getOperationNameFromEvent($event);

        if ($this->isBlacklisted($operation)) {
            return VoterInterface::VOTE_DROP;
        }

        return VoterInterface::VOTE_ABSTAIN;
    }

    abstract protected function getOperationNameFromEvent(object $event): string;

    protected function isBlacklisted(string $name): bool
    {
        foreach ($this->blacklist as $pattern) {
            if (1 !== preg_match("|$pattern|", $name)) {
                continue;
            }

            return true;
        }

        return false;
    }
}
