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
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;

class TracingAgentTest extends TestCase
{
    private \ArrayObject $spans;
    private TracerProvider $tracerProvider;

    protected function setUp(): void
    {
        $this->spans = new \ArrayObject();
        $this->tracerProvider = new TracerProvider(new SimpleSpanProcessor(new InMemoryExporter($this->spans)));
    }

    public function testItCreatesAnInvokeAgentSpan(): void
    {
        $agent = $this->buildAgent('my_agent', new TextResult('Hello'));

        $agent->call(new MessageBag());

        $this->assertCount(1, $this->spans);
        $this->assertSame('invoke_agent my_agent', $this->spans[0]->getName());
        $this->assertSame(SpanKind::KIND_INTERNAL, $this->spans[0]->getKind());
    }

    public function testItSetsGenAiAttributes(): void
    {
        $agent = $this->buildAgent('my_agent', new TextResult('Hello'));

        $agent->call(new MessageBag());

        $attributes = $this->spans[0]->getAttributes()->toArray();
        $this->assertSame('invoke_agent', $attributes['gen_ai.operation.name']);
        $this->assertSame('my_agent', $attributes['gen_ai.agent.name']);
    }

    public function testItSetsStatusOkOnSuccess(): void
    {
        $agent = $this->buildAgent('my_agent', new TextResult('Hello'));

        $agent->call(new MessageBag());

        $this->assertSame(StatusCode::STATUS_OK, $this->spans[0]->getStatus()->getCode());
    }

    public function testItRecordsExceptionsAndEndsTheSpan(): void
    {
        $exception = new \RuntimeException('boom');
        $inner = $this->createMock(AgentInterface::class);
        $inner->method('getName')->willReturn('my_agent');
        $inner->method('call')->willThrowException($exception);

        $agent = new TracingAgent($inner, $this->tracerProvider, new AgentOperationNameResolver(), new AgentAttributeProvider(), new OperationNameVoter([]));

        try {
            $agent->call(new MessageBag());
            $this->fail('Exception should have been rethrown');
        } catch (\RuntimeException $e) {
            $this->assertSame($exception, $e);
        }

        $this->assertCount(1, $this->spans);
        $this->assertSame(StatusCode::STATUS_ERROR, $this->spans[0]->getStatus()->getCode());
        $this->assertSame('exception', $this->spans[0]->getEvents()[0]->getName());
    }

    public function testItDoesNotTraceBlacklistedAgents(): void
    {
        $inner = $this->createMock(AgentInterface::class);
        $inner->method('getName')->willReturn('internal_agent');
        $inner->method('call')->willReturn(new TextResult('Hello'));

        $agent = new TracingAgent($inner, $this->tracerProvider, new AgentOperationNameResolver(), new AgentAttributeProvider(), new OperationNameVoter(['^internal_']));

        $agent->call(new MessageBag());

        $this->assertCount(0, $this->spans);
    }

    public function testItDelegatesGetName(): void
    {
        $agent = $this->buildAgent('my_agent', new TextResult('Hello'));

        $this->assertSame('my_agent', $agent->getName());
    }

    private function buildAgent(string $name, ResultInterface $result): TracingAgent
    {
        $inner = $this->createMock(AgentInterface::class);
        $inner->method('getName')->willReturn($name);
        $inner->method('call')->willReturn($result);

        return new TracingAgent($inner, $this->tracerProvider, new AgentOperationNameResolver(), new AgentAttributeProvider(), new OperationNameVoter([]));
    }
}
