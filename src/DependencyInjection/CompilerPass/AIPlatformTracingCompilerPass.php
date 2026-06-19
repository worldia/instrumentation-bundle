<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\DependencyInjection\CompilerPass;

use Instrumentation\Semantics\Attribute\PlatformAttributeProviderInterface;
use Instrumentation\Semantics\OperationName\PlatformOperationNameResolverInterface;
use Instrumentation\Tracing\AI\Platform\TracingPlatform;
use Instrumentation\Tracing\AI\Sampling\OperationNameVoter;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class AIPlatformTracingCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        /** @var array<string> $blacklist */
        $blacklist = $container->hasParameter('tracing.ai.platform.blacklist') ? $container->getParameter('tracing.ai.platform.blacklist') : [];

        foreach ($container->findTaggedServiceIds('ai.platform') as $platformId => $tags) {
            $system = $tags[0]['name'] ?? $platformId;

            // Low priority ensures TracingPlatform is the outermost decorator,
            // so the span covers the full call including any inner decorators (retry, cache, etc.).
            $definition = (new Definition(TracingPlatform::class))
                ->setDecoratedService($platformId, priority: -512)
                ->setArguments([
                    new Reference('.inner'),
                    new Reference(TracerProviderInterface::class),
                    new Reference(PlatformOperationNameResolverInterface::class),
                    new Reference(PlatformAttributeProviderInterface::class),
                    $system,
                    new Definition(OperationNameVoter::class, [$blacklist]),
                ]);

            $container->setDefinition('instrumentation.tracing.ai.platform.'.$system, $definition);
        }
    }
}
