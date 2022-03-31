# Instrumentation for Symfony

### Features

#### Tracing
- Using the official [OpenTelemetry SDK](https://github.com/open-telemetry/opentelemetry-php)
- Minimal auto-instrumentation for **requests**, console **commands** and **consumers**
- Trace context propagation from incoming **requests** to **consumers** and **outgoing http calls**
- Configurable blacklisting of requests by path to avoid useless traces, eg. `/metrics` or `/_healthz`
- Automatic log inclusion with configurable log level and channels

#### Metrics
- Using the [Prometheus Client](https://github.com/PromPHP/prometheus_client_php)
- Minimal auto-instrumentation for common **request**, **consumer** and **message** metrics (see list of provided [default metrics](./docs/metrics/default-metrics.md)).
- Autoconfigurable metric providers, see below.

#### Logging
- Adds trace context to logs for correlation, with customizable keys.

#### Baggage
- Using the official [OpenTelemetry SDK](https://github.com/open-telemetry/opentelemetry-php)
- Baggage propagation from incoming **requests** to **consumers** and **outgoing http calls**

#### Health
- A simple endpoint to expose application health (default: `/_healthz`)
- Autoconfigurable healthcheck interface to add healtchecks to be made for global application health

### Installation and configuration

```sh
composer require worldia/instrumentation-bundle
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
instrumentation:
  resource:
    service.name: my-app
  tracing:
    dsn: 'jaeger+http://jaeger:9411/api/v2/spans'
```

### Usage

- **Tracing**
    - [Simple tracing example](./docs/tracing/simple-trace.md)
    - [Simple tracing example using the static API](./docs/tracing/static-usage.md)
    - [Add Urls to your traces in error messages](./docs/tracing/add-urls-to-your-traces.md)
    - [Use upstream request id as trace id](./docs/tracing/upstream-request-id.md)
    - [Customize operation (span) name for a message](./docs/tracing/custom-operation-name-for-message.md)
- **Metrics**
    - [Adding a metric](./docs/metrics/adding-a-metric.md)
    - [Using Redis as storage adapter](./docs/metrics/using-redis-as-storage.md) (Recommended)
- **Logging**
    - [Customizing trace context log keys](./docs/logging/custom-keys.md)
- **Health**
    - [Adding a healthcheck](./docs/health/adding-a-healthcheck.md)   
- **Http**
    - [Propagating trace/baggage context in HTTP requests](./docs/http/propagating-context.md)        
