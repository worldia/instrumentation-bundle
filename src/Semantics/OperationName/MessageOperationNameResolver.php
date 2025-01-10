<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\OperationName;

use Instrumentation\Tracing\Messenger\Stamp\OperationNameStamp;
use Symfony\Component\Messenger\Envelope;

class MessageOperationNameResolver implements MessageOperationNameResolverInterface
{
    public function getOperationName(Envelope $envelope, string $operation): string
    {
        $name = str_replace('\\', '.', \get_class($envelope->getMessage()));

        /** @var OperationNameStamp|null $stamp */
        $stamp = $envelope->last(OperationNameStamp::class);

        if ($stamp) {
            $name = $stamp->getOperationName();
        }

        return \sprintf('message %s %s', $name, $operation);
    }
}
