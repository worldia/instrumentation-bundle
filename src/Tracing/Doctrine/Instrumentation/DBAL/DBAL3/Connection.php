<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Doctrine\Instrumentation\DBAL\DBAL3;

use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Instrumentation\Tracing\Doctrine\Instrumentation\DBAL\ConnectionTrait;

/**
 * Connection decorator for doctrine/dbal 3, where the transaction methods
 * return bool. The wrapping \Doctrine\DBAL\Connection propagates the return
 * value of commit(), so it must be forwarded. For doctrine/dbal 4 see
 * {@see \Instrumentation\Tracing\Doctrine\Instrumentation\DBAL\Connection}.
 *
 * This class is only ever instantiated when doctrine/dbal 3 is installed; the
 * bool return types are incompatible with the dbal 4 parent signatures, hence
 * the phpstan ignores in phpstan.neon.
 */
final class Connection extends AbstractConnectionMiddleware
{
    use ConnectionTrait;

    public function beginTransaction(): bool
    {
        return (bool) $this->traceBeginTransaction();
    }

    public function commit(): bool
    {
        return (bool) $this->traceCommit();
    }

    public function rollBack(): bool
    {
        return (bool) $this->traceRollBack();
    }
}
