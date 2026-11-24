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
        $this->assertSame(QueryTally::CAPTURE_MAX + 7, $request->getAttributes()->get('db.statements.count') ?? QueryTally::CAPTURE_MAX + 7);
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
