<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Sampling\EventSubscriber;

use Instrumentation\Tracing\Sampling\TogglableSampler;
use Instrumentation\Tracing\Sampling\Voter\VoterInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

abstract class AbstractEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            static::getEventClass() => [['onEvent', 8092]],
        ];
    }

    public function __construct(private TogglableSampler $sampler, private VoterInterface $voter)
    {
    }

    /**
     * @param RequestEvent|ConsoleCommandEvent|WorkerMessageReceivedEvent $event
     */
    public function onEvent(object $event): void
    {
        $vote = $this->voter->vote($event);

        if (VoterInterface::VOTE_RECORD === $vote) {
            $this->sampler->recordAndSampleNext();
        } elseif (VoterInterface::VOTE_DROP === $vote) {
            $this->sampler->dropNext();
        }
    }

    abstract protected static function getEventClass(): string;
}
