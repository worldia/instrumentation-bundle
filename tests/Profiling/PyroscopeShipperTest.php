<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Profiling;

use Instrumentation\Profiling\Shipping\PyroscopeShipper;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

/**
 * The upload must be on the wire when ship() returns, with no later call to finish it: a transfer left to the next request never
 * completed on a worker that is rarely profiled (stage, 2026-11-24).
 */
#[RequiresPhpExtension('curl')]
final class PyroscopeShipperTest extends TestCase
{
    public function testTheBodyIsDeliveredBeforeShipReturnsEvenWhenTheServerIsSlowAndNothingFollows(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'pyro');
        $this->assertNotFalse($file);
        $server = $this->startServer($file, 80_000, 300_000);

        try {
            $body = random_bytes(40_000);
            (new PyroscopeShipper('http://127.0.0.1:'.$server['port'], 'my app{1}', 'test'))
                ->ship($body, 1_795_000_000.5, ['route' => 'a b{c}', 'method' => 'GET', 'my key=' => 'x']);

            // The server records what it read, then waits 300 ms before answering: read it back while the answer is still pending.
            $deadline = microtime(true) + 3;
            while (microtime(true) < $deadline && '' === (string) file_get_contents($file)) {
                usleep(10_000);
            }
            $received = json_decode((string) file_get_contents($file), true);

            $this->assertIsArray($received);
            $this->assertSame(\strlen($body), $received['bytes'], 'the whole body arrived');
            $this->assertStringStartsWith('POST /ingest?', $received['request']);
            parse_str((string) parse_url('http://x'.explode(' ', $received['request'])[1], \PHP_URL_QUERY), $query);
            $this->assertSame('my_app_1_{route=a_b_c_,method=GET,my_key_=x,env=test}', $query['name']);
            $this->assertSame('pprof', $query['format']);
            $this->assertSame('1795000000', $query['from']);
        } finally {
            proc_terminate($server['process']);
            proc_close($server['process']);
            unlink($file);
        }
    }

    public function testNothingIsSentWithoutAnUrl(): void
    {
        (new PyroscopeShipper('', 'app'))->ship('x', microtime(true), []);
        $this->addToAssertionCount(1);
    }

    public function testAProfileIsDroppedPastTheUploadsInFlight(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'pyro');
        $this->assertNotFalse($file);
        $server = $this->startServer($file, 0, 2_000_000);

        try {
            $logger = new class extends \Psr\Log\AbstractLogger {
                /** @var list<string> */
                public array $warnings = [];

                public function log($level, \Stringable|string $message, array $context = []): void
                {
                    $this->warnings[] = (string) $message;
                }
            };
            $shipper = new PyroscopeShipper('http://127.0.0.1:'.$server['port'], 'app', '', 150, 1, $logger);
            $shipper->ship('one', microtime(true), []);
            $shipper->ship('two', microtime(true), []);

            $this->assertSame(['Profile dropped: too many uploads in flight'], $logger->warnings);
        } finally {
            proc_terminate($server['process']);
            proc_close($server['process']);
            unlink($file);
        }
    }

    /**
     * A one-connection HTTP server in a child process: accepts after $acceptDelayUs, writes {request line, body size} as JSON to $file,
     * answers after $answerDelayUs.
     *
     * @return array{process: resource, port: int}
     */
    private function startServer(string $file, int $acceptDelayUs, int $answerDelayUs): array
    {
        $script = <<<'PHP'
            $srv = stream_socket_server('tcp://127.0.0.1:0');
            echo substr(stream_socket_get_name($srv, false), 10), "\n";
            fflush(STDOUT);
            [$file, $acceptDelay, $answerDelay] = [$argv[1], (int) $argv[2], (int) $argv[3]];
            $c = stream_socket_accept($srv, 10);
            usleep($acceptDelay);
            stream_set_timeout($c, 3);
            $request = trim((string) fgets($c));
            $headers = '';
            while (($l = fgets($c)) !== false && $l !== "\r\n") { $headers .= $l; }
            preg_match('/Content-Length: (\d+)/i', $headers, $m);
            $len = (int) ($m[1] ?? 0);
            $body = '';
            while (strlen($body) < $len && !feof($c)) { $body .= fread($c, $len - strlen($body)); }
            file_put_contents($file, json_encode(['request' => $request, 'bytes' => strlen($body)]));
            usleep($answerDelay);
            fwrite($c, "HTTP/1.1 200 OK\r\nContent-Length: 0\r\nConnection: close\r\n\r\n");
            PHP;
        $process = proc_open([\PHP_BINARY, '-r', $script, '--', $file, (string) $acceptDelayUs, (string) $answerDelayUs], [1 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($process);
        $port = (int) trim((string) fgets($pipes[1]));
        $this->assertGreaterThan(0, $port);

        return ['process' => $process, 'port' => $port];
    }
}
