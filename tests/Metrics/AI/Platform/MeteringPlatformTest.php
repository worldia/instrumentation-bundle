<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Metrics\AI\Platform;

use Instrumentation\Metrics\AI\Platform\MeteringPlatform;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\SDK\Metrics\Data\Histogram;
use OpenTelemetry\SDK\Metrics\MeterProviderBuilder;
use OpenTelemetry\SDK\Metrics\MetricExporter\InMemoryExporter;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

class MeteringPlatformTest extends TestCase
{
    private InMemoryExporter $exporter;
    private ExportingReader $reader;
    private MeterProviderInterface $meterProvider;

    protected function setUp(): void
    {
        $this->exporter = new InMemoryExporter();
        $this->reader = new ExportingReader($this->exporter);
        $this->meterProvider = (new MeterProviderBuilder())->addReader($this->reader)->build();
    }

    public function testItRecordsTheTokenUsageHistogram(): void
    {
        $extractor = $this->createMock(TokenUsageExtractorInterface::class);
        $extractor->method('extract')->willReturn(new TokenUsage(promptTokens: 150, completionTokens: 42));

        $platform = $this->buildPlatform('openai', $extractor);
        $platform->invoke('gpt-4o', 'Hello')->asText();

        $dataPoints = $this->collectHistogramDataPoints('gen_ai.client.token.usage');
        $this->assertCount(2, $dataPoints, 'one data point per token type');

        $byType = [];
        foreach ($dataPoints as $point) {
            $attributes = $point->attributes->toArray();
            $byType[$attributes['gen_ai.token.type']] = ['sum' => $point->sum, 'attributes' => $attributes];
        }

        $this->assertSame(150, $byType['input']['sum']);
        $this->assertSame(42, $byType['output']['sum']);
        $this->assertSame('chat', $byType['input']['attributes']['gen_ai.operation.name']);
        $this->assertSame('openai', $byType['input']['attributes']['gen_ai.system']);
        $this->assertSame('gpt-4o', $byType['input']['attributes']['gen_ai.request.model']);
    }

    public function testItAcceptsAModelInstance(): void
    {
        $extractor = $this->createMock(TokenUsageExtractorInterface::class);
        $extractor->method('extract')->willReturn(new TokenUsage(promptTokens: 10, completionTokens: 5));

        $platform = $this->buildPlatform('openai', $extractor);
        $platform->invoke(new Model('gpt-4o'), 'Hello')->asText();

        $dataPoints = $this->collectHistogramDataPoints('gen_ai.client.token.usage');
        $this->assertNotEmpty($dataPoints);
        $this->assertSame('gpt-4o', $dataPoints[0]->attributes->toArray()['gen_ai.request.model']);
    }

    public function testItRecordsNothingWhenNoExtractorIsAvailable(): void
    {
        $platform = $this->buildPlatform('openai', null);
        $platform->invoke('gpt-4o', 'Hello')->asText();

        $this->assertSame([], $this->collectHistogramDataPoints('gen_ai.client.token.usage'));
    }

    public function testItRecordsNothingWhenExtractorReturnsNull(): void
    {
        $extractor = $this->createMock(TokenUsageExtractorInterface::class);
        $extractor->method('extract')->willReturn(null);

        $platform = $this->buildPlatform('openai', $extractor);
        $platform->invoke('gpt-4o', 'Hello')->asText();

        $this->assertSame([], $this->collectHistogramDataPoints('gen_ai.client.token.usage'));
    }

    public function testItRecordsTheOperationDurationHistogram(): void
    {
        $platform = $this->buildPlatform('openai', null);
        $platform->invoke('gpt-4o', 'Hello')->asText();

        $dataPoints = $this->collectHistogramDataPoints('gen_ai.client.operation.duration');
        $this->assertCount(1, $dataPoints);

        $attributes = $dataPoints[0]->attributes->toArray();
        $this->assertSame('chat', $attributes['gen_ai.operation.name']);
        $this->assertSame('openai', $attributes['gen_ai.system']);
        $this->assertSame('gpt-4o', $attributes['gen_ai.request.model']);
        $this->assertArrayNotHasKey('error.type', $attributes);
        $this->assertGreaterThanOrEqual(0, $dataPoints[0]->sum);
    }

    public function testItRecordsDurationWithErrorTypeWhenInvokeThrows(): void
    {
        $inner = $this->createMock(PlatformInterface::class);
        $inner->method('invoke')->willThrowException(new \RuntimeException('boom'));

        $platform = new MeteringPlatform($inner, $this->meterProvider, 'openai');

        try {
            $platform->invoke('gpt-4o', 'Hello');
            $this->fail('Exception should have been rethrown');
        } catch (\RuntimeException) {
        }

        $dataPoints = $this->collectHistogramDataPoints('gen_ai.client.operation.duration');
        $this->assertCount(1, $dataPoints);
        $this->assertSame(\RuntimeException::class, $dataPoints[0]->attributes->toArray()['error.type']);
    }

    /**
     * @return list<\OpenTelemetry\SDK\Metrics\Data\HistogramDataPoint>
     */
    private function collectHistogramDataPoints(string $name): array
    {
        $this->reader->collect();

        foreach ($this->exporter->collect(true) as $metric) {
            if ($metric->name === $name && $metric->data instanceof Histogram) {
                return iterator_to_array($metric->data->dataPoints, false);
            }
        }

        return [];
    }

    private function buildPlatform(string $system, TokenUsageExtractorInterface|null $extractor): MeteringPlatform
    {
        $converter = $this->createMock(ResultConverterInterface::class);
        $converter->method('convert')->willReturn(new TextResult('Hello'));
        $converter->method('getTokenUsageExtractor')->willReturn($extractor);

        $inner = $this->createMock(PlatformInterface::class);
        $inner->method('invoke')->willReturn(new DeferredResult($converter, new InMemoryRawResult([])));

        return new MeteringPlatform($inner, $this->meterProvider, $system);
    }
}
