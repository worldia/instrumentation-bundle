<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Logging\EventSubscriber;

use Instrumentation\Logging\EventSubscriber\RequestEventSubscriber;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\API\Logs\LogRecord;
use OpenTelemetry\SDK\Logs\Exporter\InMemoryExporter;
use OpenTelemetry\SDK\Logs\LoggerProvider;
use OpenTelemetry\SDK\Logs\Processor\BatchLogRecordProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelEvents;

class RequestEventSubscriberTest extends TestCase
{
    public function testItSubscribesToTerminate(): void
    {
        $this->assertArrayHasKey(KernelEvents::TERMINATE, RequestEventSubscriber::getSubscribedEvents());
    }

    public function testItFlushesBatchedLogRecordsOnTerminate(): void
    {
        $exporter = new InMemoryExporter();
        $loggerProvider = LoggerProvider::builder()
            ->addLogRecordProcessor(new BatchLogRecordProcessor($exporter, Clock::getDefault()))
            ->build();

        $loggerProvider->getLogger('test')->emit(new LogRecord('handled'));

        $this->assertCount(0, $exporter->getStorage());

        (new RequestEventSubscriber($loggerProvider))->onTerminate();

        $this->assertCount(1, $exporter->getStorage());
    }
}
