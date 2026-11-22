<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Tracing\Doctrine\Instrumentation;

use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Result;
use Instrumentation\Tracing\Bridge\MainSpanContext;
use Instrumentation\Tracing\Doctrine\Instrumentation\DBAL\Connection;
use OpenTelemetry\SDK\Trace\ImmutableSpan;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\TestCase;

/**
 * A connection outlives a request in a long-lived worker: the query spans of each request or message must hang
 * under that request's main span, not under the one the connection first saw.
 */
final class ConnectionTest extends TestCase
{
    public function testQueriesOfALaterMainSpanJoinItsTraceNotTheFirstOnes(): void
    {
        $exporter = new InMemoryExporter();
        $provider = new TracerProvider(new SimpleSpanProcessor($exporter));
        $tracer = $provider->getTracer('test');
        $mainSpanContext = new MainSpanContext();

        $connection = new Connection($provider, $this->createDriverConnection(), $mainSpanContext, [], false);

        $first = $tracer->spanBuilder('request 1')->startSpan();
        $mainSpanContext->setMainSpan($first);
        $connection->query('SELECT 1');
        $connection->query('SELECT 2');
        $first->end();

        // Between requests the worker resets the context, then serves another one on the same connection.
        $mainSpanContext->reset();
        $second = $tracer->spanBuilder('request 2')->startSpan();
        $mainSpanContext->setMainSpan($second);
        $connection->query('SELECT 3');
        $second->end();

        $spans = $this->spansByName($exporter);

        $this->assertCount(2, $spans['db.orm'], 'one db.orm span per main span, not one per connection');
        $this->assertCount(3, $spans['db.connection.query']);

        foreach ($spans['db.connection.query'] as $i => $query) {
            $expected = $i < 2 ? $first : $second;
            $this->assertSame($expected->getContext()->getTraceId(), $query->getTraceId(), \sprintf('query %d is in its own request\'s trace', $i + 1));
        }

        $this->assertSame($spans['db.orm'][0]->getSpanId(), $spans['db.connection.query'][0]->getParentSpanId());
        $this->assertSame($spans['db.orm'][0]->getSpanId(), $spans['db.connection.query'][1]->getParentSpanId(), 'a main span gets a single db.orm span');
        $this->assertSame($spans['db.orm'][1]->getSpanId(), $spans['db.connection.query'][2]->getParentSpanId());
        $this->assertSame($second->getContext()->getSpanId(), $spans['db.orm'][1]->getParentSpanId());
    }

    private function createDriverConnection(): DriverConnection
    {
        $connection = $this->createStub(DriverConnection::class);
        $connection->method('query')->willReturn($this->createStub(Result::class));

        return $connection;
    }

    /**
     * @return array<string, list<ImmutableSpan>>
     */
    private function spansByName(InMemoryExporter $exporter): array
    {
        $byName = [];
        foreach ($exporter->getSpans() as $span) {
            \assert($span instanceof ImmutableSpan);
            $byName[$span->getName()][] = $span;
        }

        return $byName;
    }
}
