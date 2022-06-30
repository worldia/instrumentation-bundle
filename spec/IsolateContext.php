<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace spec\Instrumentation;

use OpenTelemetry\Context\Context;

trait IsolateContext
{
    public function forkMainContext(): void
    {
        // For our tests to run in isolation we need them to use different contexts
        Context::storage()->fork(8534);
        Context::storage()->switch(8534);
    }

    public function restoreMainContext(): void
    {
        Context::storage()->destroy(8534);
        // Switch back to the main context since the ID no longer exist
        Context::storage()->switch(8534);
    }
}
