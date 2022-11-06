<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Sampling\EventSubscriber;

use Instrumentation\Tracing\Sampling\TogglableSampler;
use Instrumentation\Tracing\Sampling\Voter\VoterInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;

abstract class AbstractEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            static::getEventClass() => [['onRequest', 8092]],
        ];
    }

    public function __construct(private TogglableSampler $sampler, private VoterInterface $voter)
    {
    }

    public function onRequest(RequestEvent $event): void
    {
        $vote = $this->voter->vote($event);

        if (VoterInterface::VOTE_RECORD === $vote) {
            $this->sampler->recordAndSampleNext();
        } elseif (VoterInterface::VOTE_ABSTAIN === $vote) {
            $this->sampler->dropNext();
        }
    }

    abstract protected static function getEventClass(): string;
}
