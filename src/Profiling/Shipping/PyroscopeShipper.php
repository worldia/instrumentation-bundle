<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Profiling\Shipping;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Pushes a pprof profile to Pyroscope's `/ingest` endpoint (a Grafana Pyroscope, or an Alloy `pyroscope.receive_http`), as the raw
 * request body with the series labels in the `name` parameter.
 *
 * Never waits for the answer. Pyroscope 2.x acknowledges a write only when its segment is flushed, 8 to 500 ms later (median ~230 ms):
 * a synchronous POST after the response holds a worker thread that long per profiled request, and FrankenPHP answers a busy pool by
 * starting more worker threads (each a booted kernel, ~100-200 MB). So the upload is started on a curl multi handle and driven until
 * the whole body is on the wire (the name lookup, the connect and the upload, at most `$uploadDeadlineMs`: a few milliseconds were
 * enough on a local Docker network and not in a cluster, where a transfer left unfinished waited for a later request on a worker that
 * is rarely profiled). Only the answer is left to {@see self::reap()}. At most `$maxInFlight` uploads are pending: past that a profile
 * is dropped. A failure or a drop is a warning, never an exception.
 */
final class PyroscopeShipper implements ProfileShipperInterface
{
    private \CurlMultiHandle|null $multi = null;

    /** @var array<int, \CurlHandle> uploads started and not yet reaped, by handle id */
    private array $inflight = [];

    private readonly LoggerInterface $logger;
    private readonly string $url;
    private readonly string $application;
    private readonly string $environment;

    public function __construct(
        string|null $url,
        string|null $application,
        string|null $environment = '',
        private readonly int $uploadDeadlineMs = 150,
        private readonly int $maxInFlight = 8,
        LoggerInterface|null $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
        // An unset environment variable with a `default::` fallback arrives as null.
        $this->url = $url ?? '';
        $this->application = $application ?? '';
        $this->environment = $environment ?? '';
    }

    public function ship(string $pprof, float $startedAt, array $labels): void
    {
        if ('' === $this->url) {
            return;
        }
        if ('' !== $this->environment) {
            $labels += ['env' => $this->environment];
        }
        $name = $this->application.'{'.implode(',', array_map(
            static fn (string $k, string $v): string => $k.'='.preg_replace('/[^\x21-\x7E]|[{},"]/', '_', $v),
            array_keys($labels),
            $labels,
        )).'}';

        // Pyroscope stamps the profile with `from`: the second the request started, so the trace's window contains it.
        $from = (int) floor($startedAt);
        $query = http_build_query([
            'name' => $name,
            'from' => $from,
            'until' => max($from + 1, (int) ceil(microtime(true))),
            'format' => 'pprof',
            'spyName' => 'excimer',
        ]);

        $this->reap();
        if (\count($this->inflight) >= $this->maxInFlight) {
            $this->logger->warning('Profile dropped: too many uploads in flight');

            return;
        }

        $handle = curl_init(rtrim($this->url, '/').'/ingest?'.$query);
        if (false === $handle) {
            return;
        }
        curl_setopt_array($handle, [
            \CURLOPT_POST => true,
            \CURLOPT_POSTFIELDS => $pprof,
            \CURLOPT_HTTPHEADER => ['Content-Type: application/octet-stream'],
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_CONNECTTIMEOUT_MS => max(1, $this->uploadDeadlineMs * 2),
            \CURLOPT_TIMEOUT_MS => 10_000,
            \CURLOPT_NOSIGNAL => true, // ZTS: no SIGALRM-based timeouts
        ]);
        $this->multi ??= curl_multi_init();
        curl_multi_add_handle($this->multi, $handle);
        $this->inflight[spl_object_id($handle)] = $handle;

        $size = \strlen($pprof);
        $until = hrtime(true) + $this->uploadDeadlineMs * 1_000_000;
        do {
            curl_multi_exec($this->multi, $running);
            if (curl_getinfo($handle, \CURLINFO_SIZE_UPLOAD_T) >= $size) {
                break;
            }
            curl_multi_select($this->multi, 0.005);
        } while ($running && hrtime(true) < $until);
    }

    public function reap(): void
    {
        if ([] === $this->inflight || null === $this->multi) {
            return;
        }
        curl_multi_exec($this->multi, $running);
        while (false !== ($done = curl_multi_info_read($this->multi))) {
            /** @var array{msg: int, result: int, handle: \CurlHandle} $done */
            $handle = $done['handle'];
            $status = curl_getinfo($handle, \CURLINFO_RESPONSE_CODE);
            if (\CURLE_OK !== $done['result'] || 200 !== $status) {
                $this->logger->warning(\sprintf('Pyroscope ingest failed (%s, HTTP %d)', curl_strerror($done['result']), $status));
            }
            curl_multi_remove_handle($this->multi, $handle);
            unset($this->inflight[spl_object_id($handle)]);
        }
    }
}
