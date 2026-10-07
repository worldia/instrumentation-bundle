<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

use Instrumentation\Tracing\AI\Agent\Legacy\TracingAgent as LegacyTracingAgent;
use Instrumentation\Tracing\AI\Agent\TracingAgent;
use Instrumentation\Tracing\AI\Platform\TracingPlatform;
use Instrumentation\Tracing\AI\Toolbox\TracingToolbox;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set(TracingPlatform::class)
        ->abstract()
        // Explicit class: the container must never load the decorator that does not match the
        // installed symfony/ai-agent (its signature would fail at class load).
        ->set(TracingAgent::class, class_exists(Execution::class) ? TracingAgent::class : LegacyTracingAgent::class)
        ->abstract()
        ->set(TracingToolbox::class)
        ->abstract();
};
