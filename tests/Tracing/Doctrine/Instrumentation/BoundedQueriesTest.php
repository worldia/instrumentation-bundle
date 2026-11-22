<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Tracing\Doctrine\Instrumentation;

use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Instrumentation\Tracing\Bridge\MainSpanContext;
use Instrumentation\Tracing\Doctrine\Instrumentation\DBAL\Connection;
use Instrumentation\Tracing\Doctrine\Instrumentation\DBAL\QueryTally;
use OpenTelemetry\SDK\Trace\ImmutableSpan;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\TestCase;

/**
 * With a minimum duration only the slow statements are spans; every statement is counted on the main span.
 */
final class BoundedQueriesTest extends TestCase
{
    private InMemoryExporter $exporter;
    private TracerProvider $provider;
    private MainSpanContext $mainSpanContext;

    protected function setUp(): void
    {
        $this->exporter = new InMemoryExporter();
        $this->provider = new TracerProvider(new SimpleSpanProcessor($this->exporter));
        $this->mainSpanContext = new MainSpanContext();
    }

    public function testOnlyStatementsAtLeastAsSlowAsTheMinimumAreSpansAllAreCounted(): void
    {
        $connection = $this->connection(minDurationMs: 20, slowQueryMs: 40);
        $main = $this->mainSpan('request');

        $connection->query('SELECT fast');
        $connection->query('SELECT fast again');
        $connection->query('SELECT slow');
        $connection->exec('UPDATE fast');
        $main->end();

        $spans = $this->byName();
        $this->assertCount(1, $spans['db.connection.query'] ?? [], 'only the slow query is a span');
        $this->assertGreaterThanOrEqual(35_000_000, $this->durationNs($spans['db.connection.query'][0]), 'with its real duration');
        $this->assertArrayNotHasKey('db.connection.exec', $spans, 'a fast exec leaves no span');
        $this->assertCount(1, $spans['db.orm'] ?? [], 'the db.orm span is made for the slow one');
        $this->assertSame($spans['db.orm'][0]->getSpanId(), $spans['db.connection.query'][0]->getParentSpanId());

        $request = $spans['request'][0];
        $this->assertSame(4, $request->getAttributes()->get('db.statements.count'));
        $this->assertSame(1, $request->getAttributes()->get('db.statements.spans'));
        $this->assertGreaterThanOrEqual(35.0, $request->getAttributes()->get('db.statements.total_ms'));
    }

    public function testASpanStartsWhenItsStatementDidNotWhenItEnded(): void
    {
        $connection = $this->connection(minDurationMs: 20, slowQueryMs: 40);
        $main = $this->mainSpan('request');

        $before = (int) (microtime(true) * 1e9);
        $connection->query('SELECT slow');
        $after = (int) (microtime(true) * 1e9);
        $main->end();

        $span = $this->byName()['db.connection.query'][0];
        $this->assertGreaterThanOrEqual($before - 1_000_000, $span->getStartEpochNanos());
        $this->assertLessThanOrEqual($after, $span->getEndEpochNanos());
        $this->assertGreaterThan($span->getStartEpochNanos(), $span->getEndEpochNanos());
    }

    public function testEachMainSpanHasItsOwnCount(): void
    {
        $connection = $this->connection(minDurationMs: 20, slowQueryMs: 0);

        $first = $this->mainSpan('request 1');
        $connection->query('SELECT 1');
        $connection->query('SELECT 2');
        $first->end();

        $this->mainSpanContext->reset();
        $second = $this->mainSpan('request 2');
        $connection->query('SELECT 3');
        $second->end();

        $spans = $this->byName();
        $this->assertSame(2, $spans['request 1'][0]->getAttributes()->get('db.statements.count'));
        $this->assertSame(1, $spans['request 2'][0]->getAttributes()->get('db.statements.count'), 'a connection outliving a request starts a new count');
        $this->assertArrayNotHasKey('db.orm', $spans, 'nothing slow, so no db.orm span either');
    }

    public function testPreparedStatementsFollowTheSameRule(): void
    {
        $connection = $this->connection(minDurationMs: 20, slowQueryMs: 0, slowExecuteMs: 40);
        $main = $this->mainSpan('request');

        $connection->prepare('SELECT 1')->execute();
        $connection->prepare('SELECT 2')->execute();
        $main->end();

        $spans = $this->byName();
        $this->assertCount(2, $spans['db.statement.execute'] ?? [], 'both executes were slow');
        $this->assertArrayNotHasKey('db.connection.prepare', $spans, 'a fast prepare leaves no span');
        $this->assertSame($spans['db.orm'][0]->getSpanId(), $spans['db.statement.execute'][0]->getParentSpanId());
        $this->assertSame(2, $spans['request'][0]->getAttributes()->get('db.statements.count'));
        $this->assertSame(2, $spans['request'][0]->getAttributes()->get('db.statements.spans'));
    }

    public function testAStatementPreparedInOneRequestRunsUnderTheCurrentOne(): void
    {
        $connection = $this->connection(minDurationMs: 0, slowQueryMs: 0, slowExecuteMs: 0);

        $first = $this->mainSpan('request 1');
        $statement = $connection->prepare('SELECT 1');
        $first->end();

        $this->mainSpanContext->reset();
        $second = $this->mainSpan('request 2');
        $statement->execute();
        $second->end();

        $spans = $this->byName();
        $execute = $spans['db.statement.execute'][0];
        $this->assertSame($second->getContext()->getTraceId(), $execute->getTraceId(), 'in the trace of the request that ran it');
    }

    public function testWithoutAMinimumEveryStatementIsASpanAsBefore(): void
    {
        $connection = $this->connection(minDurationMs: 0, slowQueryMs: 0);
        $main = $this->mainSpan('request');

        $connection->query('SELECT 1');
        $connection->query('SELECT 2');
        $main->end();

        $this->assertCount(2, $this->byName()['db.connection.query']);
    }

    private function mainSpan(string $name): \OpenTelemetry\API\Trace\SpanInterface
    {
        $span = $this->provider->getTracer('test')->spanBuilder($name)->startSpan();
        $this->mainSpanContext->setMainSpan($span);

        return $span;
    }

    private function connection(int $minDurationMs, int $slowQueryMs, int $slowExecuteMs = 0): Connection
    {
        $result = $this->createStub(Result::class);

        $statement = $this->createStub(DriverStatement::class);
        $statement->method('execute')->willReturnCallback(static function () use ($result, $slowExecuteMs): Result {
            usleep($slowExecuteMs * 1000);

            return $result;
        });

        $driverConnection = $this->createStub(DriverConnection::class);
        $driverConnection->method('prepare')->willReturn($statement);
        $driverConnection->method('query')->willReturnCallback(static function (string $sql) use ($result, $slowQueryMs): Result {
            // Only the statements saying "slow" take their time.
            usleep(str_contains($sql, 'slow') || 0 === $slowQueryMs ? $slowQueryMs * 1000 : 0);

            return $result;
        });
        $driverConnection->method('exec')->willReturn(1);

        return new Connection($this->provider, $driverConnection, $this->mainSpanContext, [], false, new QueryTally($this->mainSpanContext, $minDurationMs));
    }

    /**
     * @return array<string, list<ImmutableSpan>>
     */
    private function byName(): array
    {
        $byName = [];
        foreach ($this->exporter->getSpans() as $span) {
            \assert($span instanceof ImmutableSpan);
            $byName[$span->getName()][] = $span;
        }

        return $byName;
    }

    private function durationNs(ImmutableSpan $span): int
    {
        return $span->getEndEpochNanos() - $span->getStartEpochNanos();
    }
}
