<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Messenger\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

final class OperationNameStamp implements StampInterface
{
    public function __construct(private string $operationName)
    {
    }

    public function getOperationName(): string
    {
        return $this->operationName;
    }
}
