<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\HttpClient;

use OpenTelemetry\API\Trace\SpanInterface;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\RedirectionException;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Component\HttpClient\Response\StreamableInterface;
use Symfony\Component\HttpClient\Response\StreamWrapper;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class TracedResponse implements ResponseInterface, StreamableInterface
{
    private string|null $content = null;
    /** @var resource|null */
    private $stream;

    public function __construct(
        private ResponseInterface $response,
        private SpanInterface $span,
    ) {
    }

    public function __destruct()
    {
        $this->endTracing();
    }

    public function getWrappedResponse(): ResponseInterface
    {
        return $this->response;
    }

    public function getStatusCode(): int
    {
        return $this->response->getStatusCode();
    }

    public function getHeaders(bool $throw = true): array
    {
        return $this->response->getHeaders($throw);
    }

    public function getContent(bool $throw = true): string
    {
        try {
            $this->content = $content = $this->response->getContent(false);

            if ($throw) {
                $this->checkStatusCode();
            }

            return $content;
        } finally {
            $this->endTracing();
        }
    }

    /**
     * @return array<int,mixed>
     */
    public function toArray(bool $throw = true): array
    {
        try {
            return $this->response->toArray($throw);
        } finally {
            $this->endTracing();
        }
    }

    public function cancel(): void
    {
        try {
            $this->response->cancel();
        } finally {
            $this->endTracing();
        }
    }

    public function getInfo(string|null $type = null): mixed
    {
        return $this->response->getInfo($type);
    }

    public function toStream(bool $throw = true)
    {
        if (\is_resource($this->stream)) {
            return $this->stream;
        }

        if ($this->response instanceof StreamableInterface) {
            $this->stream = $stream = $this->response->toStream(false);
        } else {
            $this->stream = $stream = StreamWrapper::createResource($this->response);
        }

        if ($throw) {
            $this->checkStatusCode();
        }

        return $stream;
    }

    /**
     * @param iterable<int|string,ResponseInterface> $responses
     *
     * @internal
     */
    public static function stream(HttpClientInterface $client, iterable $responses, float|null $timeout): \Generator
    {
        $wrappedResponses = [];
        $traceableMap = new \SplObjectStorage();

        foreach ($responses as $r) {
            if (!$r instanceof self) {
                throw new \TypeError(\sprintf('"%s::stream()" expects parameter 1 to be an iterable of TracedResponse objects, "%s" given.', TracingHttpClient::class, get_debug_type($r)));
            }

            $traceableMap[$r->response] = $r;
            $wrappedResponses[] = $r->response;
        }

        foreach ($client->stream($wrappedResponses, $timeout) as $r => $chunk) {
            try {
                if ($chunk->isLast() || $chunk->isTimeout()) {
                    $traceableMap[$r]->endTracing();
                }
            } catch (TransportExceptionInterface) {
                $traceableMap[$r]->endTracing();
            }

            yield $traceableMap[$r] => $chunk;
        }
    }

    protected function endTracing(): void
    {
        if (!$this->span->isRecording()) {
            return;
        }

        $endEpochNanos = null;

        /** @var array<string,mixed> $info */
        $info = $this->response->getInfo();
        if (isset($info['start_time'], $info['total_time'])) {
            $endEpochNanos = (int) (($info['start_time'] + $info['total_time']) * 1_000_000_000);
        }

        try {
            if (\is_callable($info['user_data']['on_response'] ?? null)) {
                if (empty($this->content)) {
                    $stream = $this->toStream(false);
                    $this->content = stream_get_contents($stream) ?: null;
                    rewind($stream);
                }

                \call_user_func($info['user_data']['on_response'], $this->getHeaders(false), $this->content, $this->span);
            }
        } catch (\Throwable) {
        }

        $this->span->end($endEpochNanos);
    }

    private function checkStatusCode(): void
    {
        $code = $this->getInfo('http_code');

        if (500 <= $code) {
            throw new ServerException($this);
        }

        if (400 <= $code) {
            throw new ClientException($this);
        }

        if (300 <= $code) {
            throw new RedirectionException($this);
        }
    }
}
