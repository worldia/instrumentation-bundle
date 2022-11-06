<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\DependencyInjection;

use Monolog\Level;
use Monolog\Logger;
use OpenTelemetry\SemConv\ResourceAttributes;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('instrumentation');

        $treeBuilder->getRootNode() // @phpstan-ignore-line
            ->addDefaultsIfNotSet()
            ->children()

                ->arrayNode('resource')
                    ->info('Use semantic tags defined in the OpenTelemetry specification (https://github.com/open-telemetry/opentelemetry-specification/blob/main/specification/resource/semantic_conventions/README.md)')
                    ->example([
                        ResourceAttributes::SERVICE_NAME => 'my-instrumented-app',
                        ResourceAttributes::SERVICE_VERSION => '1.2.3',
                    ])
                    ->defaultValue([
                        ResourceAttributes::SERVICE_NAME => 'app',
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
                        ->arrayNode('handlers')
                            ->defaultValue([
                                'main',
                                'console',
                            ])
                            ->scalarPrototype()->end()
                            ->info('Handlers to which the trace context processor should be bound')
                        ->end()
                        ->arrayNode('keys')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('trace')->defaultValue('context.trace')->end()
                                ->scalarNode('span')->defaultValue('context.span')->end()
                                ->scalarNode('sampled')->defaultValue('context.sampled')->end()
                                ->scalarNode('operation')->defaultValue('context.operation')->end()
                            ->end()
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
                                    ->values(method_exists(Logger::class, 'getLevels') ? Logger::getLevels() : Level::cases()) // @phpstan-ignore-line
                                    ->info(sprintf('One of the %s levels.', Logger::class))
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
                                ->arrayNode('attributes')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('server_name')
                                            ->defaultNull()
                                            ->info('Use the primary server name of the matched virtual host')
                                            ->example('example.com')

                                        ->end()
                                        ->arrayNode('headers')
                                            ->defaultValue([])
                                            ->scalarPrototype()->end()
                                            ->example([
                                                'accept',
                                                'accept-encoding',
                                            ])
                                        ->end()
                                    ->end()
                                ->end()
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
                                        '^/_wdt',
                                    ])
                                    ->scalarPrototype()->end()
                                ->end()
                                ->arrayNode('methods')
                                    ->defaultValue([
                                        'GET',
                                        'POST',
                                        'PUT',
                                        'DELETE',
                                        'PATCH',
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
                        ->arrayNode('doctrine')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('enabled')->defaultTrue()->end()
                                ->arrayNode('connections')
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
                                ->scalarNode('prefix')
                                    ->defaultNull()
                                    ->info('Set a prefix for Redis keys to avoid collisions, defaults to "metrics:<hostname>"')
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
