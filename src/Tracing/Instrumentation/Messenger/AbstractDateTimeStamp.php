<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Instrumentation\Messenger;

use Symfony\Component\Messenger\Stamp\StampInterface;

abstract class AbstractDateTimeStamp implements StampInterface
{
    private readonly \DateTimeInterface $dateTime;

    public function __construct(\DateTimeInterface|null $dateTime = null)
    {
        $this->dateTime = $dateTime ?: new \DateTime();
    }

    public function getDate(): \DateTimeInterface
    {
        return $this->dateTime;
    }
}
