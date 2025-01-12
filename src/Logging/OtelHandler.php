<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Logging;

use Instrumentation\Logging\Processor\NormalizeExceptionProcessor;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\NormalizerFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Monolog\Processor\PsrLogMessageProcessor;
use OpenTelemetry\API\Logs as API;
use OpenTelemetry\API\Logs\Severity;

class OtelHandler extends AbstractProcessingHandler
{
    /**
     * @var API\LoggerInterface[]
     **/
    private array $loggers = [];

    public function __construct(private readonly bool $enabled, private readonly API\LoggerProviderInterface $loggerProvider, Level $level, bool $bubble = true)
    {
        parent::__construct($level, $bubble);

        $this->pushProcessor(new NormalizeExceptionProcessor());
        $this->pushProcessor(new PsrLogMessageProcessor());
    }

    protected function getLogger(string $channel): API\LoggerInterface
    {
        if (!\array_key_exists($channel, $this->loggers)) {
            $this->loggers[$channel] = $this->loggerProvider->getLogger($channel);
        }

        return $this->loggers[$channel];
    }

    protected function getDefaultFormatter(): FormatterInterface
    {
        return new NormalizerFormatter();
    }

    protected function write(LogRecord $record): void
    {
        if (!$this->enabled) {
            return;
        }

        $formatted = $record['formatted'];
        $logRecord = (new API\LogRecord())
            ->setTimestamp((int) $record['datetime']->format('Uu') * 1000) // @phpstan-ignore-line
            ->setSeverityNumber(Severity::fromPsr3($record['level_name'])) // @phpstan-ignore-line
            ->setSeverityText($record['level_name']) // @phpstan-ignore-line
            ->setBody($formatted['message']) // @phpstan-ignore-line
        ;

        if (isset($formatted['context']['exception'])) { // @phpstan-ignore-line
            foreach ($formatted['context']['exception'] as $key => $value) {
                $logRecord->setAttribute($key, $value);
            }
            unset($formatted['context']['exception']);
        }

        foreach (['context', 'extra'] as $key) {
            if (isset($formatted[$key]) && \count($formatted[$key]) > 0) { // @phpstan-ignore-line
                $logRecord->setAttribute($key, $formatted[$key]);
            }
        }

        $this->getLogger($record['channel'])->emit($logRecord); // @phpstan-ignore-line
    }
}
