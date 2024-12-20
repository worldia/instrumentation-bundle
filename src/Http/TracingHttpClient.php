<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Http;

use Instrumentation\Semantics\Attribute\ClientRequestAttributeProvider;
use Instrumentation\Semantics\Attribute\ClientRequestAttributeProviderInterface;
use Instrumentation\Semantics\OperationName\ClientRequestOperationNameResolver;
use Instrumentation\Semantics\OperationName\ClientRequestOperationNameResolverInterface;
use Instrumentation\Tracing\Tracing;
use OpenTelemetry\API\Common\Time\ClockInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\TraceAttributes;
use Symfony\Component\HttpClient\DecoratorTrait;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\HttpClientTrait;
use Symfony\Component\HttpClient\Response\ResponseStream;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

final class TracingHttpClient implements HttpClientInterface
{
    use DecoratorTrait;
    use HttpClientTrait;

    private ClientRequestOperationNameResolverInterface $operationNameResolver;
    private ClientRequestAttributeProviderInterface $attributeProvider;

    /**
     * @param HttpClientInterface|array<mixed>|null $client
     */
    public function __construct(
        HttpClientInterface|array|null $client = null,
        ClientRequestOperationNameResolverInterface|null $operationNameResolver = null,
        ClientRequestAttributeProviderInterface|null $attributeProvider = null,
        int $maxHostConnections = 6,
        int $maxPendingPushes = 50,
    ) {
        if (null === $client) {
            $this->client = HttpClient::create([], $maxHostConnections, $maxPendingPushes);
        } elseif ($client instanceof HttpClientInterface) {
            $this->client = $client;
        } else {
            $this->client = HttpClient::create($client, $maxHostConnections, $maxPendingPushes);
        }

        $this->operationNameResolver = $operationNameResolver ?: new ClientRequestOperationNameResolver();
        $this->attributeProvider = $attributeProvider ?: new ClientRequestAttributeProvider();
    }

    /**
     * @param array{
     *     on_progress?: ?callable,
     *     headers?: array<string,array<string>>,
     *     extra?: array{
     *         operation_name: non-empty-string,
     *         extra_attributes: array<non-empty-string, string>,
     *         on_request: callable(array<string,array<string>>, string|null, SpanInterface): void,
     *         on_response: callable(array<string,array<string>>, string|null, SpanInterface): void,
     *     }
     * } $options
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $onProgress = $options['on_progress'] ?? null;
        $operationName = $options['extra']['operation_name'] ?? $this->operationNameResolver->getOperationName($method, $url);

        $span = Tracing::getTracer()
            ->spanBuilder($operationName)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->startSpan();

        $scope = $span->activate();

        $headers = array_merge(
            PropagationHeadersProvider::getPropagationHeaders(),
            $options['headers'] ?? []
        );

        $attributes = $this->attributeProvider->getAttributes($method, $url, $headers);
        $attributes += $options['extra']['extra_attributes'] ?? [];
        $span->setAttributes($attributes);

        $scope->detach();

        if (\is_callable($options['extra']['on_request'] ?? null)) {
            \call_user_func($options['extra']['on_request'], $headers, self::getRequestBody($options), $span);
        }

        if (isset($options['extra']['on_response'])) {
            $options['user_data']['on_response'] = $options['extra']['on_response'];
        }

        $options = array_merge($options, [
            'on_progress' => function ($dlNow, $dlSize, $info) use ($onProgress, $span, $options) {
                static $dlStarted = false;

                if (null !== $onProgress) {
                    $onProgress($dlNow, $dlSize, $info);
                }

                if (!$dlStarted && isset($info['http_code']) && 0 !== $info['http_code']) {
                    $dlStarted = true;

                    if (!isset($options['extra']['operation_name'])) {
                        $operationName = $this->operationNameResolver->getOperationName($info['http_method'], $info['url']);
                        $span->updateName($operationName);
                    }

                    $span->setAttribute(TraceAttributes::HTTP_RESPONSE_STATUS_CODE, $info['http_code']);
                    $span->setAttribute(TraceAttributes::URL_FULL, $info['url']);

                    if (\array_key_exists('total_time', $info)) {
                        $timestamp = (int) (($info['start_time'] + $info['total_time']) * ClockInterface::NANOS_PER_SECOND);
                    }
                    $span->addEvent('http.response.headers', [], $timestamp ?? null);

                    if ($info['http_code'] >= 400) {
                        $span->setStatus(StatusCode::STATUS_ERROR);
                    }
                }
            },
            'headers' => $headers,
        ]);

        return new TracedResponse($this->client->request($method, $url, $options), $span);
    }

    public function stream(ResponseInterface|iterable $responses, float|null $timeout = null): ResponseStreamInterface
    {
        if ($responses instanceof ResponseInterface) {
            $responses = [$responses];
        }

        return new ResponseStream(TracedResponse::stream($this->client, $responses, $timeout));
    }

    /**
     * @param array<mixed> $options
     */
    public function withOptions(array $options): static
    {
        return new static($this->client->withOptions($options));
    }

    /**
     * This code is extracted from the above trait to allow early body preparation.
     *
     * @see HttpClientTrait::prepareRequest()
     *
     * @param array{json?:array<mixed>,body?:array<mixed>|string|resource|\Traversable<mixed>|\Closure,normalized_headers?:array<mixed>} $options
     */
    private static function getRequestBody(array $options): string|null
    {
        $body = '';

        if (isset($options['json'])) {
            $body = self::jsonEncode($options['json']);
        } elseif (isset($options['body'])) {
            $body = $options['body'];
        }

        $body = self::normalizeBody($body);

        if (\is_string($body)
            && (string) \strlen($body) !== substr($h = $options['normalized_headers']['content-length'][0] ?? '', 16)
            && ('' !== $h || '' !== $body)
        ) {
            if ('chunked' === substr($options['normalized_headers']['transfer-encoding'][0] ?? '', \strlen('Transfer-Encoding: '))) {
                $body = self::dechunk($body);
            }

            return $body;
        }

        return null;
    }
}
