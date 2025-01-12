# Instrumentation for Symfony

Using the official [OpenTelemetry SDK](https://github.com/open-telemetry/opentelemetry-php).

### Features

- Minimal auto-instrumentation for **requests**, console **commands**, **consumers** and **doctrine**
- Trace context propagation from incoming **requests** to **consumers** and **outgoing http calls**
- Minimal metrics for **requests**, **consumers** and **messages**
- Trace context propagation for **database** requests (using [`sqlcommenter`](https://google.github.io/sqlcommenter/))

### Full working example

For a fully working example including Jaeger, Grafana, Tempo, Loki and Prometheus, check the [example directory](./example/).

### Installation and configuration

Install along your [exporter implementation](https://packagist.org/packages/open-telemetry/exporter-otlp?query=open-telemetry%2Fexporter-), eg. `open-telemetry/exporter-otlp`.

```sh
composer require worldia/instrumentation-bundle open-telemetry/exporter-otlp
```
Add to `bundles.php`.
```php
return [
    // Other bundles
    Instrumentation\InstrumentationBundle::class => ['all' => true],
];
```
Configure OTEL env vars. Replace `<your-telemetry-collector>` by yours, eg. `jaeger`, `tempo`, `otel-collector`, ...
```yaml
OTEL_SERVICE_NAME=test-app
OTEL_PHP_DETECTORS=none
OTEL_EXPORTER_OTLP_METRICS_TEMPORALITY_PREFERENCE=Delta
OTEL_EXPORTER_OTLP_PROTOCOL=http/json
OTEL_EXPORTER_OTLP_ENDPOINT=http://<your-telemetry-collector>:4318
```
Enable the extension. See the complete [configuration reference here](./docs/config-reference.md) or run ```bin/console config:dump-reference instrumentation```.
```yaml
instrumentation: ~
```

### Usage

- [Simple tracing example](./docs/tracing/simple-trace.md)
- [Add Urls to your traces in error messages](./docs/tracing/add-urls-to-your-traces.md)
- [Customize operation (span) name for a message](./docs/tracing/custom-operation-name-for-message.md)
- [Link strategy for a message](./docs/tracing/link-strategy-for-message.md)    

