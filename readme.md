# Instrumentation for Symfony

Using the official [OpenTelemetry SDK](https://github.com/open-telemetry/opentelemetry-php)

### Features

#### Tracing
- Minimal auto-instrumentation for **requests**, console **commands**, **consumers** and **doctrine**
- Trace context propagation from incoming **requests** to **consumers**, **outgoing http calls** and **databases** (using [`sqlcommenter`](https://google.github.io/sqlcommenter/))
- Configurable blacklisting of requests by path to avoid useless traces

#### Metrics
- Minimal auto-instrumentation for common **request**, **consumer** and **message** metrics (see list of provided [default metrics](./docs/metrics/default-metrics.md)).

#### Logging
- Adds trace context to logs for correlation, with customizable keys.

#### Baggage
- Using the official [OpenTelemetry SDK](https://github.com/open-telemetry/opentelemetry-php)
- Baggage propagation from incoming **requests** to **consumers** and **outgoing http calls**

### Installation and configuration

```sh
composer require worldia/instrumentation-bundle <your-exporter>
```
You will aso need to install an [exporter implementation](https://packagist.org/packages/open-telemetry/exporter-otlp?query=open-telemetry%2Fexporter-).
```

Add to ```bundles.php```:
```php
return [
    // Other bundles
    Instrumentation\InstrumentationBundle::class => ['all' => true],
];
```

**Minimal configuration**  
See the complete [configuration reference here](./docs/config-reference.md) or run ```bin/console config:dump-reference instrumentation```.

```yaml
// docker-compose.yaml

services:
  php:
    image: php:8.1
    environment:
      - OTEL_PHP_TRACES_PROCESSOR=batch
      - OTEL_TRACES_SAMPLER=parentbased_always_on
      - OTEL_TRACES_EXPORTER=otlp
      - OTEL_EXPORTER_OTLP_PROTOCOL=grpc
      - OTEL_EXPORTER_OTLP_ENDPOINT=http://jaeger:4317
  jaeger:
    image: jaegertracing/all-in-one:latest
    environment:
      COLLECTOR_OTLP_ENABLED: "true"
```

```yaml
// instrumentation.yaml

instrumentation: ~
```

### Usage

- **Tracing**
    - [Simple tracing example](./docs/tracing/simple-trace.md)
    - [Simple tracing example using the static API](./docs/tracing/static-usage.md)
    - [Add Urls to your traces in error messages](./docs/tracing/add-urls-to-your-traces.md)
    - [Use upstream request id as trace id](./docs/tracing/upstream-request-id.md)
    - [Customize operation (span) name for a message](./docs/tracing/custom-operation-name-for-message.md)
    - [Link strategy for a message](./docs/tracing/link-strategy-for-message.md)    
    - [Propagating trace/baggage context in HTTP requests](./docs/tracing/propagating-context.md)
    - [Add request / response bodies as span attributes for HTTP requests](./docs/tracing/message-bodies.md)
- **Logging**
    - [Customizing trace context log keys](./docs/logging/custom-keys.md)

