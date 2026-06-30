<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Tracing\AI\Platform;

use Instrumentation\Tracing\AI\Platform\TracingPlatform;
use Instrumentation\Tracing\Tracing;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

class TracingPlatformTest extends TestCase
{
    private \ArrayObject $spans;

    protected function setUp(): void
    {
        $this->spans = new \ArrayObject();
        $tracerProvider = new TracerProvider(new SimpleSpanProcessor(new InMemoryExporter($this->spans)));
        Tracing::setProvider($tracerProvider);
    }

    public function testItCreatesASpanWithCorrectNameAndKind(): void
    {
        $platform = $this->buildPlatform('gemini');

        $platform->invoke('gemini-2.5-pro', 'Hello')->asText();

        $this->assertCount(1, $this->spans);
        $this->assertSame('chat gemini', $this->spans[0]->getName());
        $this->assertSame(SpanKind::KIND_CLIENT, $this->spans[0]->getKind());
    }

    public function testItSetsGenAiAttributes(): void
    {
        $platform = $this->buildPlatform('perplexity');

        $platform->invoke('sonar', 'Hello')->asText();

        $attributes = $this->spans[0]->getAttributes()->toArray();
        $this->assertSame('chat', $attributes['gen_ai.operation.name']);
        $this->assertSame('perplexity', $attributes['gen_ai.system']);
        $this->assertSame('sonar', $attributes['gen_ai.request.model']);
    }

    public function testSpanEndsOnlyAfterResultIsConsumed(): void
    {
        $platform = $this->buildPlatform('gemini');

        $result = $platform->invoke('gemini-2.5-pro', 'Hello');

        $this->assertCount(0, $this->spans, 'Span must not end before result is consumed');

        $result->asText();

        $this->assertCount(1, $this->spans);
    }

    public function testItSetsStatusOkOnSuccess(): void
    {
        $platform = $this->buildPlatform('gemini');

        $platform->invoke('gemini-2.5-pro', 'Hello')->asText();

        $this->assertSame(StatusCode::STATUS_OK, $this->spans[0]->getStatus()->getCode());
    }

    public function testItAddsTokenUsageAttributesFromExtractor(): void
    {
        $extractor = $this->createMock(TokenUsageExtractorInterface::class);
        $extractor->method('extract')->willReturn(new TokenUsage(promptTokens: 150, completionTokens: 42));

        $platform = $this->buildPlatform('gemini', $extractor);

        $platform->invoke('gemini-2.5-pro', 'Hello')->asText();

        $attributes = $this->spans[0]->getAttributes()->toArray();
        $this->assertSame(150, $attributes['gen_ai.usage.input_tokens']);
        $this->assertSame(42, $attributes['gen_ai.usage.output_tokens']);
    }

    public function testItDoesNotSetTokenAttributesWhenExtractorReturnsNull(): void
    {
        $extractor = $this->createMock(TokenUsageExtractorInterface::class);
        $extractor->method('extract')->willReturn(null);

        $platform = $this->buildPlatform('gemini', $extractor);

        $platform->invoke('gemini-2.5-pro', 'Hello')->asText();

        $attributes = $this->spans[0]->getAttributes()->toArray();
        $this->assertArrayNotHasKey('gen_ai.usage.input_tokens', $attributes);
        $this->assertArrayNotHasKey('gen_ai.usage.output_tokens', $attributes);
    }

    public function testItDoesNotSetTokenAttributesWhenNoExtractor(): void
    {
        $platform = $this->buildPlatform('gemini');

        $platform->invoke('gemini-2.5-pro', 'Hello')->asText();

        $attributes = $this->spans[0]->getAttributes()->toArray();
        $this->assertArrayNotHasKey('gen_ai.usage.input_tokens', $attributes);
        $this->assertArrayNotHasKey('gen_ai.usage.output_tokens', $attributes);
    }

    public function testItEndsSpanWithErrorStatusWhenPlatformThrows(): void
    {
        $inner = $this->createMock(PlatformInterface::class);
        $inner->method('invoke')->willThrowException(new \RuntimeException('API unreachable'));

        $platform = new TracingPlatform($inner, 'gemini');

        try {
            $platform->invoke('gemini-2.5-pro', 'Hello');
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException) {
        }

        $this->assertCount(1, $this->spans);
        $this->assertSame(StatusCode::STATUS_ERROR, $this->spans[0]->getStatus()->getCode());
    }

    public function testItEndsSpanWithErrorStatusWhenConverterThrows(): void
    {
        $converter = $this->createMock(ResultConverterInterface::class);
        $converter->method('convert')->willThrowException(new \RuntimeException('Bad response'));
        $converter->method('getTokenUsageExtractor')->willReturn(null);

        $inner = $this->createMock(PlatformInterface::class);
        $inner->method('invoke')->willReturn(new DeferredResult($converter, new InMemoryRawResult()));

        $platform = new TracingPlatform($inner, 'gemini');

        try {
            $platform->invoke('gemini-2.5-pro', 'Hello')->asText();
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException) {
        }

        $this->assertCount(1, $this->spans);
        $this->assertSame(StatusCode::STATUS_ERROR, $this->spans[0]->getStatus()->getCode());
    }

    private function buildPlatform(string $system, ?TokenUsageExtractorInterface $extractor = null): TracingPlatform
    {
        $converter = $this->createMock(ResultConverterInterface::class);
        $converter->method('convert')->willReturn(new TextResult('response'));
        $converter->method('getTokenUsageExtractor')->willReturn($extractor);

        $inner = $this->createMock(PlatformInterface::class);
        $inner->method('invoke')->willReturn(new DeferredResult($converter, new InMemoryRawResult()));

        return new TracingPlatform($inner, $system);
    }
}
