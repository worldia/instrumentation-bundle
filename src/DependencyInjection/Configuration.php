<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('instrumentation');

        $treeBuilder->getRootNode() // @phpstan-ignore-line
            ->children()

                ->scalarNode('service')->end()

                ->arrayNode('health')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->scalarNode('path')->defaultValue('/_healthz')->end()
                    ->end()
                ->end()

                ->arrayNode('logging')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->arrayNode('bridges')
                            ->defaultValue([])
                            ->scalarPrototype()->end()
                        ->end()
                    ->end()
                ->end()

                ->arrayNode('tracing')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->scalarNode('dsn')->defaultValue('%env(TRACER_URL)%')->end()
                        ->arrayNode('bridges')
                            ->defaultValue([])
                            ->scalarPrototype()->end()
                        ->end()
                        ->arrayNode('request')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('enabled')->defaultTrue()->end()
                                ->arrayNode('blacklist')
                                    ->defaultValue([
                                        '^/_fragment',
                                        '^/_profiler',
                                    ])
                                    ->scalarPrototype()->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('command')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('enabled')->defaultTrue()->end()
                                ->arrayNode('blacklist')
                                    ->defaultValue([
                                        '^cache:clear$',
                                    ])
                                    ->scalarPrototype()->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('message')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('enabled')->defaultTrue()->end()
                                ->arrayNode('blacklist')
                                    ->defaultValue([])
                                    ->scalarPrototype()->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()

                ->arrayNode('metrics')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->scalarNode('path')->defaultValue('/metrics')->end()
                        ->scalarNode('namespace')->defaultValue('')->end()
                        ->arrayNode('storage')
                            ->addDefaultsIfNotSet()
                            ->beforeNormalization()
                                ->ifString()
                                ->then(fn ($v) => ['adapter' => $v])
                            ->end()
                            ->children()
                                ->enumNode('adapter')
                                    ->defaultValue('apcu')
                                    ->values(['apc', 'apcu', 'redis', 'in_memory'])
                                ->end()
                                ->scalarNode('instance')->defaultNull()->end()
                            ->end()
                            ->beforeNormalization()
                                ->ifTrue(fn ($v) => 'redis' === $v['adapter'] && !isset($v['instance']))
                                ->thenInvalid('The Redis adapter must be configured with a Redis service instance, ie: metrics.storage.adapter.type: redis, metrics.storage.adapter.instance: my_redis_service')
                            ->end()
                        ->end()
                        ->arrayNode('metrics')
                            ->arrayPrototype()
                                ->children()
                                    ->scalarNode('name')->isRequired()->end()
                                    ->scalarNode('help')->end()
                                    ->enumNode('type')
                                        ->isRequired()
                                        ->values(['gauge', 'counter', 'histogram'])
                                    ->end()
                                    ->arrayNode('labels')
                                        ->scalarPrototype()->end()
                                    ->end()
                                    ->arrayNode('buckets')
                                        ->scalarPrototype()->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('bridges')
                            ->defaultValue([])
                            ->scalarPrototype()->end()
                        ->end()
                    ->end()
                ->end()

            ->end()
        ->end();

        return $treeBuilder;
    }
}
