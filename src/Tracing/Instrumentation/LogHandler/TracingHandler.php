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
use OpenTelemetry\SDK\Trace\Span;

class TracingHandler extends AbstractProcessingHandler
{
    public const STRATEGY_MAIN_SPAN = 'main_span';
    public const STRATEGY_CURRENT_SPAN = 'current_span'; /* Experimental */

    /**
     * @var array<string>
     */
    private array $excludedChannels = [];

    /**
     * @param array<string> $channels
     */
    public function __construct(protected MainSpanContextInterface $mainSpanContext, $level = Logger::INFO, private array $channels = [], private string $strategy = self::STRATEGY_MAIN_SPAN, bool $bubble = true)
    {
        parent::__construct($level, $bubble);

        $this->pushProcessor(new PsrLogMessageProcessor());

        /*
         * Filtering channels starting with a "!" as excluded channels and remove them from the main channel list
         * @see https://symfony.com/doc/current/logging/channels_handlers.html
         */
        $this->excludedChannels = array_map(fn (string $channel) => substr($channel, 1), array_filter($this->channels, fn (string $channel) => str_starts_with($channel, '!')));
        $this->channels = array_filter($this->channels, fn (string $channel) => !str_starts_with($channel, '!'));
    }

    protected function write(array $record): void
    {
        if ($this->channels && !\in_array($record['channel'], $this->channels)) {
            return;
        }

        if ($this->excludedChannels && \in_array($record['channel'], $this->excludedChannels)) {
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
