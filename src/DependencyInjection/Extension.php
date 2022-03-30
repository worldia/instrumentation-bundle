<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\DependencyInjection;

use Instrumentation\Health\HealtcheckInterface;
use Instrumentation\Metrics\MetricProviderInterface;
use Instrumentation\Metrics\RegistryInterface;
use Instrumentation\Tracing\Instrumentation\LogHandler\TracingHandler;
use Instrumentation\Tracing\TraceUrlGenerator;
use Instrumentation\Tracing\TraceUrlGeneratorInterface;
use Prometheus\Storage\Adapter;
use Prometheus\Storage\APC;
use Prometheus\Storage\APCng;
use Prometheus\Storage\InMemory;
use Prometheus\Storage\Redis;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension as BaseExtension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class Extension extends BaseExtension implements CompilerPassInterface, PrependExtensionInterface
{
    public function getAlias(): string
    {
        return 'instrumentation';
    }

    /**
     * @param array<mixed> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $this->loadSemConv($config['resource'], $container);
        $this->loadHttp($container);

        if ($config['health']['enabled']) {
            $this->loadHealth($config['health'], $container);
        }
        if ($config['baggage']['enabled']) {
            $this->loadBaggage($config['baggage'], $container);
        }
        if ($config['tracing']['enabled']) {
            $this->loadTracing($config['tracing'], $container);
        }
        if ($config['logging']['enabled']) {
            $this->loadLogging($config['logging'], $container);
        }
        if ($config['metrics']['enabled']) {
            $this->loadMetrics($config['metrics'], $container);
        }
    }

    public function prepend(ContainerBuilder $container): void
    {
        if ($container->hasExtension('monolog')) {
            $container->prependExtensionConfig('monolog', [
                'handlers' => [
                    'tracing' => [
                        'type' => 'service',
                        'id' => TracingHandler::class,
                    ],
                ],
            ]);
        }

        if ($container->hasExtension('twig')) {
            $container->prependExtensionConfig('twig', [
                'paths' => [
                    __DIR__.'/../Tracing/Twig/Templates' => 'Twig',
                ],
            ]);
        }
    }

    public function process(ContainerBuilder $container): void
    {
        if ($container->hasParameter('tracing.request.blacklist')) {
            /** @var array<string> $tracingBlacklist */
            $tracingBlacklist = $container->getParameter('tracing.request.blacklist');
            /** @var array<string> $appPathBlacklist */
            $appPathBlacklist = $container->getParameter('app.path_blacklist');

            $container->setParameter('tracing.request.blacklist', array_merge($tracingBlacklist, $appPathBlacklist));
        }

        if ($container->hasDefinition(RegistryInterface::class)) {
            $metricProviders = $container->findTaggedServiceIds('app.metric');
            $metrics = $container->getParameter('metrics.metrics');

            foreach (array_keys($metricProviders) as $serviceId) {
                $serviceDef = $container->getDefinition($serviceId);
                /** @var class-string<MetricProviderInterface> $class */
                $class = $serviceDef->getClass();
                $providedMetrics = $class::getProvidedMetrics();

                foreach ($providedMetrics as $name => $metric) {
                    if (isset($metrics[$name])) {
                        throw new \RuntimeException(sprintf('A metric named %s is already registered.', $name));
                    }
                    $metrics[$name] = $metric;
                }
            }

            $container->setParameter('metrics.metrics', $metrics);
        }
    }

    /**
     * @param array<mixed> $config
     */
    protected function loadSemConv(array $config, ContainerBuilder $container): void
    {
        $loader = $this->getLoader('semconv', $container);

        $loader->load('semconv.php');

        $container->setParameter('app.resource_info', $config);
    }

    protected function loadHttp(ContainerBuilder $container): void
    {
        $loader = $this->getLoader('http', $container);

        $loader->load('http.php');
    }

    /**
     * @param array<mixed> $config
     */
    protected function loadHealth(array $config, ContainerBuilder $container): void
    {
        $loader = $this->getLoader('health', $container);

        $loader->load('health.php');

        $this->addPathToBlacklist($config['path'], $container);
        $container->setParameter('app.path.healtcheck', $config['path']);

        $container->registerForAutoconfiguration(HealtcheckInterface::class)->addTag('app.healthcheck');
    }

    /**
     * @param array<mixed> $config
     */
    protected function loadBaggage(array $config, ContainerBuilder $container): void
    {
        $loader = $this->getLoader('baggage', $container);

        $loader->load('baggage.php');
        $loader->load('http.php');
        $loader->load('request.php');
        $loader->load('message.php');
    }

    /**
     * @param array<mixed> $config
     */
    protected function loadTracing(array $config, ContainerBuilder $container): void
    {
        $container->setParameter('tracer.dsn', $config['dsn']);

        $loader = $this->getLoader('tracing', $container);

        $container->setParameter('tracing.request.incoming_header.name', $config['request']['incoming_header']['name'] ?? null);
        $container->setParameter('tracing.request.incoming_header.regex', $config['request']['incoming_header']['regex'] ?? null);

        $container->setParameter('tracing.logs.level', $config['logs']['level']);
        $container->setParameter('tracing.logs.channels', $config['logs']['channels']);

        $loader->load('tracing.php');
        $loader->load('http.php');

        if (isset($config['trace_url'])) {
            $container->register(TraceUrlGeneratorInterface::class)
                ->setClass(TraceUrlGenerator::class)->setArguments([
                    $config['trace_url'],
                ]);
        }

        foreach (['request', 'command', 'message'] as $feature) {
            if (!$config[$feature]['enabled']) {
                continue;
            }

            $loader->load($feature.'.php');

            $container->setParameter(sprintf('tracing.%s.blacklist', $feature), $config[$feature]['blacklist']);
        }
    }

    /**
     * @param array<mixed> $config
     */
    protected function loadLogging(array $config, ContainerBuilder $container): void
    {
        $loader = $this->getLoader('logging', $container);

        $loader->load('logging.php');

        $map = [];
        foreach ($config['keys'] as $property => $key) {
            /** @var array<string> $keys */
            $keys = preg_split('/(?<!\\\)\./', $key);
            $keys = array_map(fn (string $key) => str_replace('\.', '.', $key), $keys);
            $map[$property] = $keys;
        }

        $container->setParameter('logging.trace_context_keys', $map);
    }

    /**
     * @param array<mixed> $config
     */
    protected function loadMetrics(array $config, ContainerBuilder $container): void
    {
        $loader = $this->getLoader('metrics', $container);

        $loader->load('metrics.php');

        $container->registerForAutoconfiguration(MetricProviderInterface::class)->addTag('app.metric');

        $container->setParameter('app.path.metrics', $config['path']);
        $this->addPathToBlacklist($config['path'], $container);

        $container->setParameter('metrics.namespace', $config['namespace']);

        $metrics = [];
        foreach ($config['metrics'] as $metric) {
            $metrics[$metric['name']] = $metric;
        }

        $container->setParameter('metrics.metrics', $metrics);

        if ('redis' === $config['storage']['adapter']) {
            $container->getDefinition(Adapter::class)
                ->setFactory([Redis::class, 'fromExistingConnection'])
                ->setArguments([new Reference($config['storage']['instance'])]);
        } else {
            $map = [
                'apc' => APC::class,
                'apcu' => APCng::class,
                'in_memory' => InMemory::class,
            ];
            $container->getDefinition(Adapter::class)->setClass($map[$config['storage']['adapter']]);
        }
    }

    private function getLoader(string $component, ContainerBuilder $container): PhpFileLoader
    {
        return new PhpFileLoader($container, new FileLocator(__DIR__.'/config/'.$component));
    }

    private function addPathToBlacklist(string $path, ContainerBuilder $container): void
    {
        if (!$container->hasParameter('app.path_blacklist')) {
            $container->setParameter('app.path_blacklist', []);
        }

        /** @var array<string> $list */
        $list = $container->getParameter('app.path_blacklist');
        $list[] = $path;

        $container->setParameter('app.path_blacklist', array_unique($list));
    }
}
