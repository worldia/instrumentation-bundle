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
 * A request asked for it has its statements captured as events of its main span: the text, the time and the database of each, in
 * order, with the values of a statement run as it is masked. Any other request has none.
 */
final class StatementCaptureTest extends TestCase
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

    /**
     * @return iterable<string, array{int}> the minimum duration: 0 puts every statement in the trace, 5 bounds them
     */
    public static function modes(): iterable
    {
        yield 'a span per statement' => [0];
        yield 'bounded spans' => [5];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('modes')]
    public function testTheStatementsOfACapturedRequestAreEventsOfItsMainSpanInOrder(int $minDurationMs): void
    {
        $tally = new QueryTally($this->mainSpanContext, $minDurationMs);
        $connection = $this->connection($tally);
        $tally->capture(true);
        $main = $this->mainSpan('request');

        $connection->query("SELECT * /* traceparent=00-x */ FROM users WHERE email = 'alice@example.com' AND id = 42");
        $connection->prepare('SELECT * FROM places WHERE code = $1')->execute();
        $connection->exec('UPDATE t SET a = 1');
        $main->end();

        $events = $this->events($this->byName()['request'][0]);
        $this->assertCount(3, $events);
        $this->assertSame([1, 2, 3], array_map(static fn (array $e): mixed => $e['db.statement.index'], $events));
        $this->assertSame("SELECT * FROM users WHERE email = '?' AND id = ?", $events[0]['db.statement.text'], 'literals masked, the propagation comment gone');
        $this->assertSame('SELECT * FROM places WHERE code = $1', $events[1]['db.statement.text'], 'a prepared text as it is');
        $this->assertSame('UPDATE t SET a = ?', $events[2]['db.statement.text']);
        $this->assertSame('app', $events[0]['db.namespace']);
        $this->assertIsFloat($events[0]['db.statement.duration_ms']);
    }

    public function testNothingIsCapturedUnlessTheRequestWasAsked(): void
    {
        $tally = new QueryTally($this->mainSpanContext, 0);
        $connection = $this->connection($tally);
        $main = $this->mainSpan('request');

        $connection->query('SELECT 1');
        $connection->prepare('SELECT 2')->execute();
        $main->end();

        $this->assertSame([], $this->events($this->byName()['request'][0]));

        $tally->capture(true);
        $tally->capture(false);
        $second = $this->mainSpan('request 2');
        $connection->query('SELECT 3');
        $second->end();
        $this->assertSame([], $this->events($this->byName()['request 2'][0]), 'stopped');
    }

    public function testTheCaptureIsBoundedAndSaysWhatItDropped(): void
    {
        $tally = new QueryTally($this->mainSpanContext, 0);
        $connection = $this->connection($tally);
        $tally->capture(true);
        $main = $this->mainSpan('request');

        for ($i = 0; $i < QueryTally::CAPTURE_MAX + 7; ++$i) {
            $connection->query('SELECT '.$i);
        }
        $main->end();

        $request = $this->byName()['request'][0];
        $this->assertCount(QueryTally::CAPTURE_MAX, $this->events($request));
        $this->assertSame(7, $request->getAttributes()->get('db.statements.capture_dropped'));
    }

    public function testEachMainSpanStartsItsOwnCapture(): void
    {
        $tally = new QueryTally($this->mainSpanContext, 0);
        $connection = $this->connection($tally);
        $tally->capture(true);

        $first = $this->mainSpan('request 1');
        $connection->query('SELECT 1');
        $first->end();
        $this->mainSpanContext->reset();
        $second = $this->mainSpan('request 2');
        $connection->query('SELECT 2');
        $second->end();

        $spans = $this->byName();
        $this->assertCount(1, $this->events($spans['request 1'][0]));
        $this->assertSame(1, $this->events($spans['request 2'][0])[0]['db.statement.index'], 'a new main span, a new count');
    }

    /**
     * @return iterable<string, array{string, bool, string}>
     */
    public static function texts(): iterable
    {
        yield 'a string with an escaped quote' => ["SELECT 1 WHERE a = 'it\\'s secret' AND b = 2", false, 'SELECT ? WHERE a = \'?\' AND b = ?'];
        yield 'a doubled quote' => ["SELECT * FROM t WHERE a = 'it''s secret'", true, "SELECT * FROM t WHERE a = '?'"];
        yield 'an E string' => ["SELECT * FROM t WHERE a = E'x\\'y secret'", true, "SELECT * FROM t WHERE a = E'?'"];
        yield 'a dollar-quoted string' => ['SELECT $tag$secret '."'".' text$tag$', true, "SELECT '?'"];
        yield 'a quote never closed' => ["SELECT * FROM t WHERE a = 'secret that goes on", true, "SELECT * FROM t WHERE a = '?'"];
        yield 'a prepared statement keeps its placeholders and its numbers' => ['SELECT * FROM t WHERE a = $1 AND b = ? LIMIT 10', true, 'SELECT * FROM t WHERE a = $1 AND b = ? LIMIT 10'];
        yield 'a prepared statement with an inline literal' => ["SELECT * FROM t WHERE token = 'abc'", true, "SELECT * FROM t WHERE token = '?'"];
        yield 'a statement run as it is loses its numbers' => ['SELECT * FROM t WHERE id = 42 LIMIT 10', false, 'SELECT * FROM t WHERE id = ? LIMIT ?'];
        yield 'non-ASCII text' => ["SELECT * FROM t WHERE nom = 'Zürich' ORDER BY é", true, "SELECT * FROM t WHERE nom = '?' ORDER BY é"];
        yield 'a comment never closed' => ['SELECT 1 /* traceparent=x', true, 'SELECT 1'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('texts')]
    public function testWhatIsCapturedHasItsQuotedLiteralsMasked(string $sql, bool $prepared, string $expected): void
    {
        $tally = new QueryTally($this->mainSpanContext, 0);
        $tally->capture(true);
        $main = $this->mainSpan('request');

        $tally->captureStatement($sql, [], 1_000_000, $prepared);
        $main->end();

        $this->assertSame($expected, $this->events($this->byName()['request'][0])[0]['db.statement.text']);
    }

    public function testALongStatementIsNotKeptAndATruncatedLiteralDoesNotLeak(): void
    {
        $tally = new QueryTally($this->mainSpanContext, 0);
        $tally->capture(true);
        $main = $this->mainSpan('request');

        $tally->captureStatement("INSERT INTO t VALUES ('".str_repeat('secret ', 20000)."')", [], 1, true);
        $tally->captureStatement("SELECT '".str_repeat('secret ', 100)."'", [], 1, true);
        $main->end();

        $events = $this->events($this->byName()['request'][0]);
        $this->assertMatchesRegularExpression('/^\[a statement of \d+ bytes\]$/', $events[0]['db.statement.text']);
        $this->assertSame("SELECT '?'", $events[1]['db.statement.text'], 'cut after the literal is masked, never in the middle of it');
    }

    public function testTheTransactionsAreNotStatementsAndAFailingStatementKeepsItsOwnError(): void
    {
        $tally = new QueryTally($this->mainSpanContext, 0);
        $driverConnection = $this->createStub(DriverConnection::class);
        $driverConnection->method('query')->willThrowException(new \RuntimeException('the database is down'));
        $connection = new Connection($this->provider, $driverConnection, $this->mainSpanContext, [], false, $tally);
        $tally->capture(true);
        $main = $this->mainSpan('request');

        $connection->beginTransaction();
        $connection->commit();
        try {
            $connection->query('SELECT 1');
            $this->fail('the statement fails');
        } catch (\RuntimeException $e) {
            $this->assertSame('the database is down', $e->getMessage());
        }
        $main->end();

        $events = $this->events($this->byName()['request'][0]);
        $this->assertCount(1, $events, 'the failing statement is captured, begin and commit are not');
        $this->assertSame('SELECT ?', $events[0]['db.statement.text']);
    }

    private function mainSpan(string $name): \OpenTelemetry\API\Trace\SpanInterface
    {
        $span = $this->provider->getTracer('test')->spanBuilder($name)->startSpan();
        $this->mainSpanContext->setMainSpan($span);

        return $span;
    }

    private function connection(QueryTally $tally): Connection
    {
        $result = $this->createStub(Result::class);
        $statement = $this->createStub(DriverStatement::class);
        $statement->method('execute')->willReturn($result);
        $driverConnection = $this->createStub(DriverConnection::class);
        $driverConnection->method('prepare')->willReturn($statement);
        $driverConnection->method('query')->willReturn($result);
        $driverConnection->method('exec')->willReturn(1);

        return new Connection($this->provider, $driverConnection, $this->mainSpanContext, ['db.namespace' => 'app'], false, $tally);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function events(ImmutableSpan $span): array
    {
        $events = [];
        foreach ($span->getEvents() as $event) {
            if ('db.statement' === $event->getName()) {
                $events[] = $event->getAttributes()->toArray();
            }
        }

        return $events;
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
}
