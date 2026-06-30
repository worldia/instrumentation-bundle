<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\DependencyInjection\CompilerPass;

use Instrumentation\Tracing\AI\Platform\TracingPlatform;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class AIPlatformTracingCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach (array_keys($container->findTaggedServiceIds('ai.platform')) as $platformId) {
            $system = str_contains($platformId, 'ai.platform.')
                ? substr($platformId, \strlen('ai.platform.'))
                : $platformId;

            $definition = (new Definition(TracingPlatform::class))
                ->setDecoratedService($platformId, priority: -512)
                ->setArguments([new Reference('.inner'), $system]);

            $container->setDefinition('instrumentation.tracing.ai.platform.'.$system, $definition);
        }
    }
}
