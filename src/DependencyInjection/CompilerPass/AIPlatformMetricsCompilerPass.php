<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\DependencyInjection\CompilerPass;

use Instrumentation\Metrics\AI\Platform\MeteringPlatform;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class AIPlatformMetricsCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds('ai.platform') as $platformId => $tags) {
            $system = $tags[0]['name'] ?? $platformId;

            $definition = (new Definition(MeteringPlatform::class))
                ->setDecoratedService($platformId, priority: -512)
                ->setArguments([
                    new Reference('.inner'),
                    new Reference(MeterProviderInterface::class),
                    $system,
                ]);

            $container->setDefinition('instrumentation.metrics.ai.platform.'.$system, $definition);
        }
    }
}
