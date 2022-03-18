# InstrumentationBundle for Symfony

#### Features

- Minimal auto-instrumentation for requests, console commands and consumers
- Trace context propagation from incoming requests, to consumers and outgoing http calls

#### Minimal configuration

```yaml
instrumentation:
  tracing:
    # Accepts any DSN handled by OpenTelemetry's ExporterFactory.
    # See: https://github.com/open-telemetry/opentelemetry-php/blob/main/src/SDK/Trace/ExporterFactory.php
    #
    # Example: Zipkin to Jaeger
    dsn: 'zipkin+http://jaeger:9411/api/v2/spans?serviceName=rooms'
```

#### Bridges

**Google Cloud Platform**
- **Logging**: The GCP bridge includes a `monolog` formatter to include trace information in logs.
- **Tracing**: Handles transformation and propagation of the `X-Cloud-Trace-Context` header set by GCP load balancers.
