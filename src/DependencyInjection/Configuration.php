<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\DependencyInjection;

use Monolog\Logger;
use OpenTelemetry\SemConv\ResourceAttributes;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('instrumentation');

        $treeBuilder->getRootNode() // @phpstan-ignore-line
            ->children()

                ->arrayNode('resource')
                    ->isRequired()
                    ->info('Use semantic tags defined in the OpenTelemetry specification (https://github.com/open-telemetry/opentelemetry-specification/blob/main/specification/resource/semantic_conventions/README.md)')
                    ->example([
                        'service.name' => 'my-instrumented-app',
                        'service.version' => '1.2.3',
                    ])
                    ->scalarPrototype()->end()
                    ->beforeNormalization()
                        ->ifTrue(fn ($v) => !isset($v[ResourceAttributes::SERVICE_NAME]))
                        ->thenInvalid(sprintf('You must provide the "%s" attribute in resource info.', ResourceAttributes::SERVICE_NAME))
                    ->end()
                ->end()

                ->arrayNode('baggage')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                    ->end()
                ->end()

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
                        ->scalarNode('dsn')
                            ->defaultValue('%env(TRACER_URL)%')
                            ->info('Accepts any DSN handled by OpenTelemetry\'s ExporterFactory. See: https://github.com/open-telemetry/opentelemetry-php/blob/main/src/SDK/Trace/ExporterFactory.php')
                            ->example('zipkin+http://jaeger:9411/api/v2/spans')
                        ->end()
                        ->scalarNode('trace_url')
                            ->info('Allows you to have links to your traces generated in error messages and twig.')
                            ->example('http://localhost:16682/trace/{traceId}')
                        ->end()
                        ->arrayNode('logs')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->enumNode('level')
                                    ->defaultValue(Logger::INFO)
                                    ->values(Logger::getLevels())
                                    ->info(sprintf('One of %s the levels.', Logger::class))
                                ->end()
                                ->arrayNode('channels')
                                    ->defaultValue([])
                                    ->scalarPrototype()->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('request')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('enabled')->defaultTrue()->end()
                                ->arrayNode('incoming_header')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('name')->end()
                                        ->scalarNode('regex')->end()
                                    ->end()
                                ->end()
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
                                        '^assets:install$',
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
                        ->scalarNode('namespace')
                            ->defaultValue('')
                            ->info('Prefix added to all metrics.')
                        ->end()
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
                                ->scalarNode('instance')
                                    ->defaultNull()
                                    ->info('When using the redis adapter, set "instance" to a service id that is an instance of \Redis')
                                ->end()
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
                                    ->scalarNode('help')->isRequired()->end()
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
                    ->end()
                ->end()

            ->end()
        ->end();

        return $treeBuilder;
    }
}
