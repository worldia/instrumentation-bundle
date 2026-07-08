<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Functional;

use Instrumentation\InstrumentationBundle;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as ContractsEventDispatcherInterface;
use Tests\Instrumentation\Functional\Stub\DummyPlatform;

/**
 * Minimal kernel that boots FrameworkBundle + InstrumentationBundle so we can
 * prove the bundle's DI container compiles and boots under the installed Symfony
 * (7.4 or 8.x, depending on the CI leg), including the AI platform decoration.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new InstrumentationBundle();
    }

    public function build(ContainerBuilder $container): void
    {
        // On Symfony 8, the core `event_dispatcher` service is provided by
        // dependency-injection's ServicesBundle, which drops it when
        // symfony/event-dispatcher is only a *dev* dependency of the root package
        // (as it is inside this bundle, where framework-bundle is require-dev).
        // A real consuming app requires framework-bundle normally, so this never
        // happens there. Re-add it here so the boot test reflects real usage.
        // No-op on Symfony 7.4, where event_dispatcher is always registered.
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                if ($container->has('event_dispatcher') || !class_exists(EventDispatcher::class)) {
                    return;
                }

                $container->register('event_dispatcher', EventDispatcher::class)
                    ->setPublic(true)
                    ->addTag('container.hot_path')
                    ->addTag('event_dispatcher.dispatcher', ['name' => 'event_dispatcher']);

                foreach ([EventDispatcherInterface::class, ContractsEventDispatcherInterface::class, PsrEventDispatcherInterface::class] as $alias) {
                    if (interface_exists($alias)) {
                        $container->setAlias($alias, 'event_dispatcher');
                    }
                }
            }
        }, PassConfig::TYPE_BEFORE_OPTIMIZATION, 100);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/instrumentation-bundle-tests/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/instrumentation-bundle-tests/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'router' => ['utf8' => true],
            // Required: the bundle always decorates serializer.normalizer.problem
            // and the HttpClientInterface service when tracing is enabled.
            'serializer' => ['enabled' => true],
            'http_client' => ['enabled' => true],
        ]);

        $container->extension('instrumentation', [
            // Keep the boot minimal: logging would pull in monolog wiring.
            'logging' => ['enabled' => false],
            'tracing' => [
                'enabled' => true,
                'ai' => ['enabled' => true],
            ],
            'metrics' => [
                'enabled' => true,
                'ai' => ['enabled' => true],
            ],
        ]);

        // A stub service tagged `ai.platform` so the AI tracing/metrics compiler
        // passes have a real service to decorate during container compilation.
        $container->services()
            ->set('app.platform.openai', DummyPlatform::class)
            ->public()
            ->tag('ai.platform', ['name' => 'openai']);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
    }
}
