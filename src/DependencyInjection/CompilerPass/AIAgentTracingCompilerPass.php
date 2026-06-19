<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\DependencyInjection\CompilerPass;

use Instrumentation\Semantics\Attribute\AgentAttributeProviderInterface;
use Instrumentation\Semantics\Attribute\ToolAttributeProviderInterface;
use Instrumentation\Tracing\AI\Agent\TracingAgent;
use Instrumentation\Tracing\AI\Toolbox\TracingToolbox;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class AIAgentTracingCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // Low priority ensures the tracing decorator is the outermost one, so the
        // span covers the full call including any inner decorators (e.g. the
        // profiler's traceable decorators registered at -1024).
        foreach ($container->findTaggedServiceIds('ai.agent') as $agentId => $tags) {
            $name = $tags[0]['name'] ?? $agentId;

            $definition = (new Definition(TracingAgent::class))
                ->setDecoratedService($agentId, priority: -512)
                ->setArguments([
                    new Reference('.inner'),
                    new Reference(TracerProviderInterface::class),
                    new Reference(AgentAttributeProviderInterface::class),
                ]);

            $container->setDefinition('instrumentation.tracing.ai.agent.'.$name, $definition);
        }

        foreach ($container->findTaggedServiceIds('ai.toolbox') as $toolboxId => $tags) {
            $name = $tags[0]['name'] ?? $toolboxId;

            $definition = (new Definition(TracingToolbox::class))
                ->setDecoratedService($toolboxId, priority: -512)
                ->setArguments([
                    new Reference('.inner'),
                    new Reference(TracerProviderInterface::class),
                    new Reference(ToolAttributeProviderInterface::class),
                ]);

            $container->setDefinition('instrumentation.tracing.ai.toolbox.'.$name, $definition);
        }
    }
}
