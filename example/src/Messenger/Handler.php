<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace App\Messenger;

use Instrumentation\Logging\Logging;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class Handler
{
    public function __construct(private readonly TracerProviderInterface $tracerProvider)
    {
    }

    public function __invoke(Message $message)
    {
        $span = $this->tracerProvider->getTracer('test')->spanBuilder('handling')->startSpan();
        Logging::getLogger()->alert('Processing');
        $span->end();
    }
}
