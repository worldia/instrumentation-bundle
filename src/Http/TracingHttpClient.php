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
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\TraceAttributes;
use Symfony\Component\HttpClient\DecoratorTrait;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class TracingHttpClient implements HttpClientInterface
{
    use DecoratorTrait;

    private ClientRequestOperationNameResolverInterface $operationNameResolver;
    private ClientRequestAttributeProviderInterface $attributeProvider;

    /**
     * @param HttpClientInterface|array<mixed>|null $client
     */
    public function __construct(
        private string $serviceName,
        HttpClientInterface|array|null $client = null,
        ClientRequestOperationNameResolverInterface $operationNameResolver = null,
        ClientRequestAttributeProviderInterface $attributeProvider = null,
        int $maxHostConnections = 6,
        int $maxPendingPushes = 50
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
     * @param array<mixed> $options
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $onProgress = $options['on_progress'] ?? null;
        $headers = $options['headers'] ?? [];
        $operationName = $this->operationNameResolver->getOperationName($method, $url, $this->serviceName);
        $attributes = $this->attributeProvider->getAttributes($method, $url, $this->serviceName, $headers);

        $span = Tracing::getTracer()
            ->spanBuilder($operationName)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttributes($attributes)
            ->startSpan();

        $options = array_merge($options, [
            'on_progress' => function ($dlNow, $dlSize, $info) use ($onProgress, $span) {
                static $dlStarted = false;

                if (null !== $onProgress) {
                    $onProgress($dlNow, $dlSize, $info);
                }

                if (!$dlStarted && isset($info['http_code']) && 0 !== $info['http_code']) {
                    $dlStarted = true;
                    $span->setAttribute(TraceAttributes::HTTP_STATUS_CODE, $info['http_code']);
                    $span->setAttribute(TraceAttributes::HTTP_URL, $info['url']);

                    if ($info['http_code'] >= 400) {
                        $span->setStatus(StatusCode::STATUS_ERROR);
                    }
                }
            },
            'headers' => array_merge(
                PropagationHeadersProvider::getPropagationHeaders(),
                $headers
            ),
        ]);

        return new TracedResponse($this->client->request($method, $url, $options), $span);
    }

    /**
     * @param array<mixed> $options
     */
    public function withOptions(array $options): static
    {
        return new static($this->serviceName, $this->client->withOptions($options));
    }
}
