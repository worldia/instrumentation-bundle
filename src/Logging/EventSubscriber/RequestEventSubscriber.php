<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Logging\EventSubscriber;

use OpenTelemetry\API\Logs\LoggerProviderInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Long-running workers (FrankenPHP, RoadRunner) serve many requests per process: export the
 * request's log records when the kernel terminates rather than when the batch fills or the
 * process exits.
 */
class RequestEventSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly LoggerProviderInterface $loggerProvider)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // After the tracing subscriber (-512), so records logged while terminating are included.
            KernelEvents::TERMINATE => [['onTerminate', -1024]],
        ];
    }

    public function onTerminate(): void
    {
        if (method_exists($this->loggerProvider, 'forceFlush')) {
            $this->loggerProvider->forceFlush();
        }
    }
}
