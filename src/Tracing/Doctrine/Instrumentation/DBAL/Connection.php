<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Doctrine\Instrumentation\DBAL;

use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;

/**
 * Connection decorator for doctrine/dbal 4, where the transaction methods
 * return void. For doctrine/dbal 3 see {@see DBAL3\Connection}.
 */
final class Connection extends AbstractConnectionMiddleware
{
    use ConnectionTrait;

    public function beginTransaction(): void
    {
        $this->traceBeginTransaction();
    }

    public function commit(): void
    {
        $this->traceCommit();
    }

    public function rollBack(): void
    {
        $this->traceRollBack();
    }
}
