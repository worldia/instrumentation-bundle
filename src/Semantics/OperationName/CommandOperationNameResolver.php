<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\OperationName;

use Symfony\Component\Console\Command\Command;

class CommandOperationNameResolver implements CommandOperationNameResolverInterface
{
    public function getOperationName(?Command $command): string
    {
        $name = 'unknown-command';

        if ($command) {
            $name = $command->getName() ?: $command->getDefaultName();
        }

        return sprintf('cli %s', $name);
    }
}
