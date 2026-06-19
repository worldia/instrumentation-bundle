<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\DependencyInjection\CompilerPass;

use Instrumentation\Tracing\Doctrine\Instrumentation\DBAL\Middleware as InstrumentationMiddleware;
use Instrumentation\Tracing\Doctrine\Propagation\DBAL\Middleware as PropagationMiddleware;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class DoctrineTracingCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('tracing.doctrine.connections') || !$container->hasParameter('doctrine.connections')) {
            return;
        }

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
