<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Instrumentation\LogHandler;

use Instrumentation\Tracing\Instrumentation\MainSpanContextInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\SDK\Trace\Span;

class TracingHandler extends AbstractProcessingHandler
{
    public const STRATEGY_MAIN_SPAN = 'main_span';
    public const STRATEGY_CURRENT_SPAN = 'current_span'; /* Experimental */

    /**
     * @param array<string> $channels
     */
    public function __construct(protected TracerProviderInterface $tracerProvider, protected MainSpanContextInterface $mainSpanContext, $level = Logger::INFO, private array $channels = [], private string $strategy = self::STRATEGY_MAIN_SPAN, bool $bubble = true)
    {
        parent::__construct($level, $bubble);

        $this->pushProcessor(new PsrLogMessageProcessor());
    }

    protected function write(array $record): void
    {
        if ($this->channels && !\in_array($record['channel'], $this->channels)) {
            return;
        }

        $span = match ($this->strategy) {
            self::STRATEGY_MAIN_SPAN => $this->mainSpanContext->getMainSpan(),
            self::STRATEGY_CURRENT_SPAN => Span::getCurrent(),
            default => throw new \InvalidArgumentException(sprintf('Unkown strategy "%s".', $this->strategy))
        };

        if (isset($record['context']['exception']) && $record['context']['exception'] instanceof \Throwable) {
            $span->recordException($record['context']['exception'], ['raw_stacktrace' => $record['context']['exception']->getTraceAsString()]);
        } else {
            $span->addEvent($record['message']);
        }
    }
}
