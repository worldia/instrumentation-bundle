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
- Minimal auto-instrumentation for common **request metrics** (including number of requests handled in total, currently handling, and response codes), **consumer metrics** (number of active consumers) and **message metrics** (messages currently consuming, total messages handled and total messages failed).
- Autoconfigurable metric providers, see below.

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
    dsn: 'zipkin+http://jaeger:9411/api/v2/spans'
```

### Usage

- **Tracing**
    - [Simple tracing example](./docs/tracing/simple-trace.md)
    - [Simple tracing example using the static API](./docs/tracing/static-usage.md)
- **Metrics**
    - [Yet to be added]

#### Bridges

**Google Cloud Platform**
- **Logging**: The GCP bridge includes a `monolog` formatter to include trace information in logs.
- **Tracing**: Handles transformation and propagation of the `X-Cloud-Trace-Context` header set by GCP load balancers.
