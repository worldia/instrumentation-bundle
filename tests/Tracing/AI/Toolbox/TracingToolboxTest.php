<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Tracing\AI\Toolbox;

use Instrumentation\Semantics\Attribute\ToolAttributeProvider;
use Instrumentation\Tracing\AI\Toolbox\TracingToolbox;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Result\ToolCall;

class TracingToolboxTest extends TestCase
{
    private \ArrayObject $spans;
    private TracerProvider $tracerProvider;

    protected function setUp(): void
    {
        $this->spans = new \ArrayObject();
        $this->tracerProvider = new TracerProvider(new SimpleSpanProcessor(new InMemoryExporter($this->spans)));
    }

    public function testItCreatesAnExecuteToolSpan(): void
    {
        $toolCall = new ToolCall('call_123', 'get_weather', ['city' => 'Paris']);
        $toolbox = $this->buildToolbox($toolCall, new ToolResult($toolCall, 'sunny'));

        $toolbox->execute($toolCall);

        $this->assertCount(1, $this->spans);
        $this->assertSame('execute_tool get_weather', $this->spans[0]->getName());
        $this->assertSame(SpanKind::KIND_INTERNAL, $this->spans[0]->getKind());
    }

    public function testItSetsGenAiAttributes(): void
    {
        $toolCall = new ToolCall('call_123', 'get_weather', ['city' => 'Paris']);
        $toolbox = $this->buildToolbox($toolCall, new ToolResult($toolCall, 'sunny'));

        $toolbox->execute($toolCall);

        $attributes = $this->spans[0]->getAttributes()->toArray();
        $this->assertSame('execute_tool', $attributes['gen_ai.operation.name']);
        $this->assertSame('get_weather', $attributes['gen_ai.tool.name']);
        $this->assertSame('call_123', $attributes['gen_ai.tool.call.id']);
    }

    public function testItSetsStatusOkOnSuccess(): void
    {
        $toolCall = new ToolCall('call_123', 'get_weather');
        $toolbox = $this->buildToolbox($toolCall, new ToolResult($toolCall, 'sunny'));

        $toolbox->execute($toolCall);

        $this->assertSame(StatusCode::STATUS_OK, $this->spans[0]->getStatus()->getCode());
    }

    public function testItRecordsExceptionsAndEndsTheSpan(): void
    {
        $toolCall = new ToolCall('call_123', 'get_weather');
        $exception = new \RuntimeException('boom');

        $inner = $this->createMock(ToolboxInterface::class);
        $inner->method('execute')->willThrowException($exception);

        $toolbox = new TracingToolbox($inner, $this->tracerProvider, new ToolAttributeProvider());

        try {
            $toolbox->execute($toolCall);
            $this->fail('Exception should have been rethrown');
        } catch (\RuntimeException $e) {
            $this->assertSame($exception, $e);
        }

        $this->assertCount(1, $this->spans);
        $this->assertSame(StatusCode::STATUS_ERROR, $this->spans[0]->getStatus()->getCode());
        $this->assertSame('exception', $this->spans[0]->getEvents()[0]->getName());
    }

    public function testItDelegatesGetTools(): void
    {
        $inner = $this->createMock(ToolboxInterface::class);
        $inner->method('getTools')->willReturn([]);

        $toolbox = new TracingToolbox($inner, $this->tracerProvider, new ToolAttributeProvider());

        $this->assertSame([], $toolbox->getTools());
    }

    private function buildToolbox(ToolCall $expected, ToolResult $result): TracingToolbox
    {
        $inner = $this->createMock(ToolboxInterface::class);
        $inner->method('execute')->with($expected)->willReturn($result);

        return new TracingToolbox($inner, $this->tracerProvider, new ToolAttributeProvider());
    }
}
