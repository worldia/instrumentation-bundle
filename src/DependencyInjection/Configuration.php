<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\DependencyInjection;

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
                        ResourceAttributes::SERVICE_NAME => 'my_instrumented_app',
                        ResourceAttributes::SERVICE_VERSION => '1.2.3',
                    ])
                    ->scalarPrototype()->end()
                    ->defaultValue([
                        ResourceAttributes::SERVICE_NAME => '%env(default:instrumentation.default_service_name:OTEL_SERVICE_NAME)%',
                    ])
                ->end()

                ->arrayNode('baggage')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                    ->end()
                ->end()

                ->arrayNode('logging')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                    ->end()
                ->end()

                ->arrayNode('tracing')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->scalarNode('trace_url')
                            ->info('Allows you to have links to your traces generated in error messages and twig.')
                            ->example('http://localhost:16682/trace/{traceId}')
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
                                        ->arrayNode('request_headers')
                                            ->info('Incoming request headers to add as span attributes')
                                            ->defaultValue([])
                                            ->scalarPrototype()->end()
                                            ->example([
                                                'accept',
                                                'accept-encoding',
                                            ])
                                        ->end()
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
                                ->booleanNode('flush_spans_after_handling')
                                    ->info('Whether exporter should be flushed after each message')
                                    ->defaultTrue()
                                ->end()
                                ->arrayNode('blacklist')
                                    ->defaultValue([])
                                    ->scalarPrototype()->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('http')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('enabled')->defaultTrue()->end()
                                ->booleanNode('propagate_by_default')
                                    ->info('Whether trace context should be propagated by default for outgoing requests')
                                    ->defaultTrue()
                                ->end()
                                ->arrayNode('request_headers')
                                    ->info('Outgoing request headers to add as span attributes')
                                    ->defaultValue([])
                                    ->scalarPrototype()->end()
                                    ->example([
                                        'accept',
                                        'accept-encoding',
                                    ])
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('doctrine')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('instrumentation')->defaultFalse()->end()
                                ->booleanNode('propagation')->defaultFalse()->end()
                                ->booleanNode('log_queries')->defaultFalse()->end()
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
                        ->arrayNode('message')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('enabled')->defaultFalse()->end()
                            ->end()
                        ->end()
                        ->arrayNode('request')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('enabled')->defaultFalse()->end()
                                ->arrayNode('blacklist')
                                    ->defaultValue([
                                        '^/_fragment',
                                        '^/_profiler',
                                        '^/_wdt',
                                    ])
                                    ->scalarPrototype()->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()

            ->end()
            ->beforeNormalization()
                ->ifTrue(fn ($v) => false === \array_key_exists(ResourceAttributes::SERVICE_NAME, $v))
                ->then(function ($v) {
                    $v['resource'][ResourceAttributes::SERVICE_NAME] = '%env(default:instrumentation.default_service_name:OTEL_SERVICE_NAME)%';

                    return $v;
                })
            ->end()
        ->end();

        return $treeBuilder;
    }
}
