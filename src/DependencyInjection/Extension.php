<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\DependencyInjection;

use Instrumentation\Tracing\Bridge\TraceUrlGenerator;
use Instrumentation\Tracing\Bridge\TraceUrlGeneratorInterface;
use Instrumentation\Tracing\Doctrine\Instrumentation\DBAL\Middleware as InstrumentationMiddleware;
use Instrumentation\Tracing\Doctrine\Propagation\DBAL\Middleware as PropagationMiddleware;
use Instrumentation\Tracing\Request\EventListener\AddUserEventSubscriber;
use OpenTelemetry\SDK\Trace\SpanLimitsBuilder;
use Symfony\Bundle\MonologBundle\MonologBundle;
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

        $container->setParameter('instrumentation.default_service_name', 'app');
        $container->setParameter('service.name', $config['resource']['service.name']);

        $this->loadSemConv($config['resource'], $container);

        if ($this->isConfigEnabled($container, $config['logging'])) {
            $this->loadLogging($config['baggage'], $container);
        }
        if ($this->isConfigEnabled($container, $config['baggage'])) {
            $this->loadBaggage($config['baggage'], $container);
        }
        if ($this->isConfigEnabled($container, $config['tracing'])) {
            $this->loadTracing($config['tracing'], $container);
        }
        if ($this->isConfigEnabled($container, $config['metrics'])) {
            $this->loadMetrics($config['metrics'], $container);
        }
    }

    public function prepend(ContainerBuilder $container): void
    {
        if ($container->hasExtension('twig')) {
            $container->prependExtensionConfig('twig', [
                'paths' => [
                    __DIR__.'/../Tracing/Bridge/Twig/Templates' => 'Twig',
                    __DIR__.'/../Tracing/Bridge/Profiler/Templates' => 'InstrumentationDataCollector',
                ],
            ]);
        }
    }

    public function process(ContainerBuilder $container): void
    {
        if ($container->hasParameter('tracing.doctrine.connections') && $container->hasParameter('doctrine.connections')) {
            /** @var array<string> $connectionsToTrace */
            $connectionsToTrace = $container->getParameter('tracing.doctrine.connections');

            /** @var array<string,string> $connections */
            $connections = $container->getParameter('doctrine.connections');

            if (empty($connectionsToTrace)) {
                $connectionsToTrace = array_keys($connections);
            }

            foreach ($connectionsToTrace as $connection) {
                $serviceId = \sprintf('doctrine.dbal.%s_connection', $connection);

                if (!\in_array($serviceId, $connections, true)) {
                    throw new \InvalidArgumentException(\sprintf('No such connection: "%s".', $connection));
                }

                $configDef = $container->getDefinition(\sprintf('%s.configuration', $serviceId));

                $middlewares = [];
                foreach ($configDef->getMethodCalls() as $call) {
                    [$method, $arguments] = $call;
                    if ('setMiddlewares' === $method) {
                        $middlewares = array_merge($middlewares, $arguments[0]);
                    }
                }

                $addedMiddlewares = [];

                if ($container->getParameter('tracing.doctrine.instrumentation')) {
                    $addedMiddlewares[] = new Reference(InstrumentationMiddleware::class);
                }
                if ($container->getParameter('tracing.doctrine.propagation')) {
                    $addedMiddlewares[] = new Reference(PropagationMiddleware::class);
                }

                $configDef
                    ->removeMethodCall('setMiddlewares')
                    ->addMethodCall('setMiddlewares', [array_merge($middlewares, $addedMiddlewares)]);
            }
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

        $container->setParameter('tracing.request.attributes.server_name', null);
        $container->setParameter('tracing.request.attributes.request_headers', []);
        $container->setParameter('tracing.http.attributes.request_headers', []);
    }

    /**
     * @param array<mixed> $config
     */
    protected function loadBaggage(array $config, ContainerBuilder $container): void
    {
        $loader = $this->getLoader('baggage', $container);

        $loader->load('http.php');
        $loader->load('request.php');
        $loader->load('message.php');
    }

    /**
     * @param array<mixed> $config
     */
    protected function loadTracing(array $config, ContainerBuilder $container): void
    {
        $loader = $this->getLoader('tracing', $container);

        $container->setParameter('tracing.request.attributes.server_name', $config['request']['attributes']['server_name']);
        $container->setParameter('tracing.request.attributes.request_headers', array_map(fn (string $value): string => strtolower($value), $config['request']['attributes']['request_headers']));
        $container->setParameter('tracing.message.flush_spans_after_handling', $config['message']['flush_spans_after_handling']);
        $container->setParameter('tracing.http.propagate_by_default', $config['http']['propagate_by_default']);
        $container->setParameter('tracing.http.attributes.request_headers', array_map(fn (string $value): string => strtolower($value), $config['http']['attributes']['request_headers']));

        $loader->load('tracing.php');
        $loader->load('http.php');

        if (isset($config['trace_url'])) {
            $container->register(TraceUrlGeneratorInterface::class)
                ->setClass(TraceUrlGenerator::class)->setArguments([
                    $config['trace_url'],
                ]);
        }

        $config['doctrine']['enabled'] = $config['doctrine']['instrumentation'] || $config['doctrine']['propagation'];

        foreach (['request', 'command', 'message', 'doctrine'] as $feature) {
            if (!$this->isConfigEnabled($container, $config[$feature])) {
                continue;
            }

            $loader->load($feature.'.php');

            foreach (['blacklist', 'methods'] as $property) {
                if (isset($config[$feature][$property])) {
                    $container->setParameter(\sprintf('tracing.%s.%s', $feature, $property), $config[$feature][$property]);
                }
            }
        }

        $container->setParameter('tracing.doctrine.connections', $config['doctrine']['connections']);
        $container->setParameter('tracing.doctrine.log_queries', $config['doctrine']['log_queries']);
        $container->setParameter('tracing.doctrine.propagation', $config['doctrine']['propagation']);
        $container->setParameter('tracing.doctrine.instrumentation', $config['doctrine']['instrumentation']);

        if (!$config['request']['attributes']['user']) {
            $container->removeDefinition(AddUserEventSubscriber::class);
        } elseif (method_exists(SpanLimitsBuilder::class, 'retainGeneralIdentityAttributes')) {
            $container->getDefinition(SpanLimitsBuilder::class)->addMethodCall('retainGeneralIdentityAttributes');
        }
    }

    /**
     * @param array<mixed> $config
     */
    protected function loadLogging(array $config, ContainerBuilder $container): void
    {
        if (!class_exists(MonologBundle::class)) {
            throw new \InvalidArgumentException('Logging requires "symfony/monolog-bundle". Did you forget to add it?');
        }

        $loader = $this->getLoader('logging', $container);

        $loader->load('logging.php');
    }

    /**
     * @param array<mixed> $config
     */
    protected function loadMetrics(array $config, ContainerBuilder $container): void
    {
        $loader = $this->getLoader('metrics', $container);

        $loader->load('metrics.php');

        if ($this->isConfigEnabled($container, $config['message'])) {
            $loader->load('message.php');
        }
        if ($this->isConfigEnabled($container, $config['request'])) {
            $container->setParameter('metrics.request.blacklist', $config['request']['blacklist']);
            $loader->load('request.php');
        }
    }

    private function getLoader(string $component, ContainerBuilder $container): PhpFileLoader
    {
        $env = $container->getParameter('kernel.environment');

        if (!\is_string($env)) {
            $env = null;
        }

        return new PhpFileLoader($container, new FileLocator(__DIR__.'/config/'.$component), $env);
    }
}
