<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Profiling\Label;

use Symfony\Component\HttpFoundation\Request;

/**
 * Adds series labels to the profile of a request (the channel, the tenant, ...). Keep the values few: a profiling backend keeps
 * one series per distinct label set. Autoconfigured: implement it and it is used.
 */
interface ProfileLabelProviderInterface
{
    /** @return array<string, string> */
    public function labels(Request $request): array;
}
