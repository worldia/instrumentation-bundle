<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Bridge\Sentry\Tracing;

use Http\Discovery\HttpClientDiscovery;
use Http\Discovery\Psr17FactoryDiscovery;
use Nyholm\Dsn\DsnParser;
use OpenTelemetry\SDK\Trace\Behavior\HttpSpanExporterTrait;
use OpenTelemetry\SDK\Trace\Behavior\UsesSpanConverterTrait;
use OpenTelemetry\SDK\Trace\EventInterface;
use OpenTelemetry\SDK\Trace\SpanDataInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SemConv\ResourceAttributes;
use OpenTelemetry\SemConv\TraceAttributes;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Class SentryExporter - implements the export interface for data transfer via Sentry protocol.
 */
class Exporter implements SpanExporterInterface
{
    use HttpSpanExporterTrait;
    use UsesSpanConverterTrait;

    public function __construct(
        string $endpointUrl,
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        SpanConverter $spanConverter = null
    ) {
        $this->setEndpointUrl($endpointUrl);
        $this->setClient($client);
        $this->setRequestFactory($requestFactory);
        $this->setStreamFactory($streamFactory);
        $this->setSpanConverter($spanConverter ?? new SpanConverter());
    }

    /**
     * @param iterable<SpanDataInterface> $spans
     *
     * @throws \JsonException
     */
    protected function serializeTrace(iterable $spans): string
    {
        if (!$rootSpan = self::getRootSpan($spans)) {
            return '';
        }

        $resource = $rootSpan->getResource()->getAttributes()->toArray();
        $eventId = $rootSpan->getTraceId();

        $envelopeHeader = json_encode([
            'event_id' => $eventId,
            'sent_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ], \JSON_THROW_ON_ERROR);

        $itemHeader = json_encode([
            'type' => 'transaction',
            'content_type' => 'application/json',
        ], \JSON_THROW_ON_ERROR);

        $event = json_encode([
            'event_id' => $eventId,
            'type' => 'transaction',
            'start_timestamp' => Util::nanosToSeconds($rootSpan->getStartEpochNanos()),
            'timestamp' => Util::nanosToSeconds($rootSpan->getEndEpochNanos()),
            'platform' => 'php',
            'sdk' => [
                'name' => $rootSpan->getInstrumentationScope()->getName(),
                'version' => $rootSpan->getInstrumentationScope()->getVersion() ?: '1.0.0',
            ],
            'release' => $resource[ResourceAttributes::SERVICE_VERSION] ?? null,
            'environment' => $resource[ResourceAttributes::DEPLOYMENT_ENVIRONMENT] ?? null,
            // 'server_name' => $resource[TraceAttributes::HTTP_SERVER_NAME] ?? 'some-server-name',
            'contexts' => [
                'os' => [
                    'name' => php_uname('s'),
                    'version' => php_uname('r'),
                    'build' => php_uname('v'),
                    'kernel_version' => php_uname('a'),
                ],
                'runtime' => [
                    'name' => 'php',
                    'version' => \PHP_VERSION,
                ],
                'trace' => $this->convertSpan($rootSpan)[0],
            ],
            'transaction' => $rootSpan->getName(),
            // 'status' => Util::toSentrySpanStatus($rootSpan->getStatus()->getCode()),
            'request' => self::getRequestData($rootSpan),
            'user' => self::getUserData($rootSpan),
            'spans' => $this->getSpanConverter()->convert(self::getOtherSpans($spans, $rootSpan)),
            'breadcrumbs' => ['values' => self::getBreadcrumbs($spans)],
            'tags' => $rootSpan->getAttributes()->toArray(),
            'exception' => ['values' => self::getExceptions($spans)],
        ], \JSON_THROW_ON_ERROR);

        return sprintf("%s\n%s\n%s", $envelopeHeader, $itemHeader, $event);
    }

    /**
     * @param iterable<SpanDataInterface> $spans
     *
     * @throws \JsonException
     */
    protected function marshallRequest(iterable $spans): RequestInterface
    {
        $dsn = DsnParser::parse($this->getEndpointUrl());

        $data = [
            'sentry_version' => 7,
            'sentry_client' => 'open-telemetry-php/0.0.1',
            'sentry_key' => $dsn->getUser(),
        ];

        $authHeader = [];

        foreach ($data as $headerKey => $headerValue) {
            $authHeader[] = $headerKey.'='.$headerValue;
        }

        $request = $this->createRequest('POST', sprintf('%s://%s/api%s/envelope/', $dsn->getScheme(), $dsn->getHost(), $dsn->getPath()))
            ->withBody(
                $this->createStream(
                    $this->serializeTrace($spans)
                )
            )
            ->withHeader('Content-Type', 'application/x-sentry-envelope')
            ->withAddedHeader('x-sentry-auth', 'Sentry '.implode(', ', $authHeader));

        return $request;
    }

    /** {@inheritdoc} */
    public static function fromConnectionString(string $endpointUrl, string $name, string $args): SpanExporterInterface
    {
        return new self(
            $endpointUrl,
            HttpClientDiscovery::find(),
            Psr17FactoryDiscovery::findRequestFactory(),
            Psr17FactoryDiscovery::findStreamFactory()
        );
    }

    /**
     * @param iterable<SpanDataInterface> $spans
     */
    private static function getRootSpan(iterable $spans): ?SpanDataInterface
    {
        if (!$span = reset($spans)) {
            return null;
        }

        foreach ($spans as $span) {
            if (!$span->getParentContext()->isValid()) {
                return $span;
            }
        }

        return null;
    }

    /**
     * @param iterable<SpanDataInterface> $spans
     *
     * @return iterable<SpanDataInterface>
     */
    private static function getOtherSpans(iterable $spans, SpanDataInterface $excluded): iterable
    {
        foreach ($spans as $span) {
            if ($span !== $excluded) {
                yield $span;
            }
        }
    }

    /**
     * @param iterable<SpanDataInterface> $spans
     *
     * @return array<mixed>
     */
    private static function getBreadcrumbs(iterable $spans): array
    {
        $breadcrumbs = [];
        foreach ($spans as $span) {
            foreach (self::getBreadcrumbsForSpan($span) as $breadcrumb) {
                $breadcrumbs[] = $breadcrumb;
            }
        }
        usort($breadcrumbs, fn (array $a, array $b) => $a['timestamp'] <=> $b['timestamp']);

        return $breadcrumbs;
    }

    /**
     * @return array<mixed>
     */
    private static function getBreadcrumbsForSpan(SpanDataInterface $span): array
    {
        return array_map(fn (EventInterface $event): array => self::getBreadcrumb($event), $span->getEvents());
    }

    /**
     * @return array<string,mixed>
     */
    private static function getBreadcrumb(EventInterface $event): array
    {
        $attributes = $event->getAttributes()->toArray();
        $level = null;
        $category = null;

        if (isset($attributes['_severity'])) {
            $level = Util::toSentrySeverity($attributes['_severity']);
        }
        if (isset($attributes['_category'])) {
            $category = $attributes['_category'];
        }

        unset($attributes['_severity'], $attributes['_category']);

        $result = [
            'type' => 'debug',
            'category' => $category,
            'level' => $level,
            'message' => $event->getName(),
            'data' => $attributes,
            'timestamp' => Util::nanosToSeconds($event->getEpochNanos()),
        ];

        return $result;
    }

    /**
     * @param iterable<SpanDataInterface> $spans
     *
     * @return array<int,string|array<string,string>>
     */
    private static function getExceptions(iterable $spans): array
    {
        $exceptions = [];
        foreach ($spans as $span) {
            foreach (self::getExceptionsForSpan($span) as $exception) {
                $exceptions[] = $exception;
            }
        }

        return $exceptions;
    }

    /**
     * @return iterable<array<string,string>>
     */
    private static function getExceptionsForSpan(SpanDataInterface $span): iterable
    {
        foreach ($span->getEvents() as $event) {
            if ($exception = self::getException($event)) {
                yield $exception;
            }
        }
    }

    /**
     * @return array<string,string>
     */
    private static function getException(EventInterface $event): ?array
    {
        if ('exception' !== $event->getName()) {
            return null;
        }

        $attributes = $event->getAttributes()->toArray();

        return array_filter([
            'type' => $attributes[TraceAttributes::EXCEPTION_TYPE] ?? null,
            'value' => $event->getName(),
            'stacktrace' => $attributes['raw_stacktrace'] ?? null,
        ]);
    }

    /**
     * @return array<string,string|array<string,string>>
     */
    private static function getRequestData(SpanDataInterface $span): array
    {
        $attributes = $span->getAttributes()->toArray();

        $url = $attributes[TraceAttributes::HTTP_URL] ?? null;

        if (!$url) {
            $url = sprintf(
                '%s://%s%s',
                $attributes[TraceAttributes::HTTP_SCHEME] ?? '',
                $attributes[TraceAttributes::HTTP_HOST] ?? '',
                $attributes[TraceAttributes::HTTP_TARGET] ?? '',
            );
        }

        return array_filter([
            'url' => empty($url) ? null : $url,
            'method' => $attributes[TraceAttributes::HTTP_METHOD] ?? null,
            'headers' => [
                'user-agent' => $attributes[TraceAttributes::HTTP_USER_AGENT] ?? null,
            ],
            // 'query_string'
            // 'data'
        ]);
    }

    /**
     * @return array<string,string>
     */
    private static function getUserData(SpanDataInterface $span): array
    {
        $attributes = $span->getAttributes()->toArray();

        return array_filter([
            'ip_address' => $attributes[TraceAttributes::HTTP_CLIENT_IP] ?? null,
            'username' => $attributes[TraceAttributes::ENDUSER_ID] ?? null,
            'role' => $attributes[TraceAttributes::ENDUSER_ROLE] ?? null,
            'scope' => $attributes[TraceAttributes::ENDUSER_SCOPE] ?? null,
        ]);
    }
}
