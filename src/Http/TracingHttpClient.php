<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Http;

use Instrumentation\Semantics\OperationName\ClientRequestOperationNameResolver;
use Instrumentation\Semantics\OperationName\ClientRequestOperationNameResolverInterface;
use Instrumentation\Tracing\Tracing;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\TraceAttributes;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

final class TracingHttpClient implements HttpClientInterface
{
    private HttpClientInterface $decorated;
    private ClientRequestOperationNameResolverInterface $operationNameResolver;

    /**
     * @param HttpClientInterface|array<mixed>|null $decorated
     */
    public function __construct(
        private string $serviceName,
        HttpClientInterface|array|null $decorated = null,
        ClientRequestOperationNameResolverInterface $operationNameResolver = null
    ) {
        if (null === $decorated) {
            $this->decorated = HttpClient::create();
        } elseif ($decorated instanceof HttpClientInterface) {
            $this->decorated = $decorated;
        } else {
            $this->decorated = HttpClient::create($decorated);
        }

        $this->operationNameResolver = $operationNameResolver ?: new ClientRequestOperationNameResolver();
    }

    /**
     * @param array<mixed> $options
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $onProgress = $options['on_progress'] ?? null;
        $headers = $options['headers'] ?? [];
        $operationName = $this->operationNameResolver->getOperationName($method, $url, $this->serviceName);

        $span = Tracing::getTracer()
            ->spanBuilder($operationName)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttributes([
                TraceAttributes::HTTP_METHOD => $method,
                TraceAttributes::HTTP_URL => $url,
                TraceAttributes::NET_PEER_NAME => $this->serviceName,
            ])
            ->startSpan();

        $span->activate();

        $options = array_merge($options, [
            'on_progress' => function ($dlNow, $dlSize, $info) use ($onProgress, $span) {
                if (null !== $onProgress) {
                    $onProgress($dlNow, $dlSize, $info);
                }

                if (isset($info['http_code']) && 0 !== $info['http_code']) {
                    $span->setAttribute(TraceAttributes::HTTP_URL, $info['url']);
                    $span->setAttribute(TraceAttributes::HTTP_STATUS_CODE, $info['http_code']);

                    if ($info['http_code'] >= 400) {
                        $span->setStatus(StatusCode::STATUS_ERROR);
                    }

                    $span->end();
                }
            },
            'headers' => array_merge(
                PropagationHeadersProvider::getPropagationHeaders(),
                $headers
            ),
        ]);

        return $this->decorated->request($method, $url, $options);
    }

    public function stream(ResponseInterface|iterable $responses, float $timeout = null): ResponseStreamInterface
    {
        return $this->decorated->stream($responses, $timeout);
    }

    /**
     * @param array<mixed> $options
     */
    public function withOptions(array $options): static
    {
        return new static($this->serviceName, $this->decorated->withOptions($options));
    }
}
