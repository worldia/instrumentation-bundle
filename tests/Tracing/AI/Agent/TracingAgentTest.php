<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Tracing\AI\Agent;

use Instrumentation\Semantics\Attribute\AgentAttributeProvider;
use Instrumentation\Semantics\OperationName\AgentOperationNameResolver;
use Instrumentation\Tracing\AI\Agent\TracingAgent;
use Instrumentation\Tracing\AI\Sampling\OperationNameVoter;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\Update\Result;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\TextResult;

class TracingAgentTest extends TestCase
{
    private \ArrayObject $spans;
    private TracerProvider $tracerProvider;

    protected function setUp(): void
    {
        if (!class_exists(Execution::class)) {
            $this->markTestSkipped('symfony/ai-agent < 0.13 runs eagerly, covered by Legacy\TracingAgentTest.');
        }

        $this->spans = new \ArrayObject();
        $this->tracerProvider = new TracerProvider(new SimpleSpanProcessor(new InMemoryExporter($this->spans)));
    }

    public function testTheSpanCoversTheConsumptionNotTheCall(): void
    {
        $execution = $this->tracingAgent($this->inner('my_agent', static function (): \Generator {
            yield new Result(new TextResult('Hello'));
        }))->call(new MessageBag());

        $this->assertCount(0, $this->spans);

        $this->assertSame('Hello', $execution->getContent());

        $this->assertCount(1, $this->spans);
        $this->assertSame('invoke_agent my_agent', $this->spans[0]->getName());
        $this->assertSame(SpanKind::KIND_INTERNAL, $this->spans[0]->getKind());
        $this->assertSame(StatusCode::STATUS_OK, $this->spans[0]->getStatus()->getCode());

        $attributes = $this->spans[0]->getAttributes()->toArray();
        $this->assertSame('invoke_agent', $attributes['gen_ai.operation.name']);
        $this->assertSame('my_agent', $attributes['gen_ai.agent.name']);
    }

    public function testSpansEmittedWhileTheAgentRunsAreItsChildren(): void
    {
        $tracer = $this->tracerProvider->getTracer('test');

        $execution = $this->tracingAgent($this->inner('my_agent', static function () use ($tracer): \Generator {
            $tracer->spanBuilder('chat model')->startSpan()->end();
            yield new Progress('model');
            $tracer->spanBuilder('execute_tool search')->startSpan()->end();
            yield new Result(new TextResult('Hello'));
        }))->call(new MessageBag());

        $currentWhileHandlingUpdates = [];
        foreach ($execution as $update) {
            $currentWhileHandlingUpdates[] = Span::getCurrent()->getContext()->getSpanId();
        }

        $this->assertCount(3, $this->spans);
        [$chat, $tool, $agent] = $this->spans;
        $this->assertSame('invoke_agent my_agent', $agent->getName());
        $this->assertSame($agent->getSpanId(), $chat->getParentSpanId());
        $this->assertSame($agent->getSpanId(), $tool->getParentSpanId());

        // The caller handles each update outside the agent span.
        $this->assertNotContains($agent->getSpanId(), $currentWhileHandlingUpdates);
    }

    public function testTheSpanIsAChildOfTheContextActiveAtCallTime(): void
    {
        $parent = $this->tracerProvider->getTracer('test')->spanBuilder('controller')->startSpan();
        $scope = $parent->activate();

        try {
            $execution = $this->tracingAgent($this->inner('my_agent', static function (): \Generator {
                yield new Result(new TextResult('Hello'));
            }))->call(new MessageBag());
        } finally {
            $scope->detach();
        }

        $execution->getResult();

        $this->assertSame($parent->getContext()->getSpanId(), $this->spans[0]->getParentSpanId());
    }

    public function testItRecordsExceptionsRaisedWhileConsuming(): void
    {
        $exception = new \RuntimeException('boom');

        $execution = $this->tracingAgent($this->inner('my_agent', static function () use ($exception): \Generator {
            yield new Progress('model');
            throw $exception;
        }))->call(new MessageBag());

        try {
            $execution->getResult();
            $this->fail('Exception should have been rethrown');
        } catch (\RuntimeException $e) {
            $this->assertSame($exception, $e);
        }

        $this->assertCount(1, $this->spans);
        $this->assertSame(StatusCode::STATUS_ERROR, $this->spans[0]->getStatus()->getCode());
        $this->assertSame('exception', $this->spans[0]->getEvents()[0]->getName());
    }

    public function testItRecordsExceptionsRaisedByTheCall(): void
    {
        $exception = new \InvalidArgumentException('bad input');
        $inner = $this->createMock(AgentInterface::class);
        $inner->method('getName')->willReturn('my_agent');
        $inner->method('call')->willThrowException($exception);

        try {
            $this->tracingAgent($inner)->call(new MessageBag());
            $this->fail('Exception should have been rethrown');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame($exception, $e);
        }

        $this->assertCount(1, $this->spans);
        $this->assertSame(StatusCode::STATUS_ERROR, $this->spans[0]->getStatus()->getCode());
    }

    public function testItKeepsTheStreamedFlag(): void
    {
        $execution = $this->tracingAgent($this->inner('my_agent', static function (): \Generator {
            yield new Result(new TextResult('Hello'));
        }, streamed: true))->call(new MessageBag());

        $this->assertTrue($execution->isStreamed());
    }

    public function testItDoesNotTraceBlacklistedAgents(): void
    {
        $inner = $this->inner('internal_agent', static function (): \Generator {
            yield new Result(new TextResult('Hello'));
        });
        $innerExecution = $inner->call(new MessageBag());
        $inner = $this->createMock(AgentInterface::class);
        $inner->method('getName')->willReturn('internal_agent');
        $inner->method('call')->willReturn($innerExecution);

        $agent = new TracingAgent($inner, $this->tracerProvider, new AgentOperationNameResolver(), new AgentAttributeProvider(), new OperationNameVoter(['^internal_']));

        $execution = $agent->call(new MessageBag());
        $execution->getResult();

        $this->assertSame($innerExecution, $execution);
        $this->assertCount(0, $this->spans);
    }

    public function testItDelegatesGetName(): void
    {
        $this->assertSame('my_agent', $this->tracingAgent($this->inner('my_agent', static fn (): \Generator => yield from []))->getName());
    }

    private function tracingAgent(AgentInterface $inner): TracingAgent
    {
        return new TracingAgent($inner, $this->tracerProvider, new AgentOperationNameResolver(), new AgentAttributeProvider(), new OperationNameVoter([]));
    }

    /**
     * @param \Closure(): \Generator $updates
     */
    private function inner(string $name, \Closure $updates, bool $streamed = false): AgentInterface
    {
        $inner = $this->createMock(AgentInterface::class);
        $inner->method('getName')->willReturn($name);
        $inner->method('call')->willReturnCallback(static fn (): Execution => new Execution($updates, $streamed));

        return $inner;
    }
}
