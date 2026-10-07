# Configuration reference

As output by `bin/console config:dump-reference instrumentation`.

```yaml
# Default configuration for extension with alias: "instrumentation"
instrumentation:
  # Use semantic tags defined in the OpenTelemetry specification (https://github.com/open-telemetry/opentelemetry-specification/blob/main/specification/resource/semantic_conventions/README.md)
  resource:
    # Default:
    service.name: %env(default:instrumentation.default_service_name:OTEL_SERVICE_NAME)%

    # Examples:
    # service.name:        my_instrumented_app
    # service.version:     1.2.3
  baggage:
    enabled: false
  logging:
    enabled: true

    # Whether log records should be exported after each request (kernel.terminate)
    flush_after_request: true
  tracing:
    enabled: true

    # Allows you to have links to your traces generated in error messages and twig.
    trace_url: ~ # Example: 'http://localhost:16682/trace/{traceId}'
    request:
      enabled: true

      # Whether exporter should be flushed after each request (kernel.terminate)
      flush_spans_after_terminate: true
      attributes:
        # Use the primary server name of the matched virtual host
        server_name: null # Example: example.com
        headers: []

          # Examples:
          # - accept
          # - accept-encoding
      blacklist:
        # Defaults:
        - ^/_fragment
        - ^/_profiler
        - ^/_wdt
      methods:
        # Defaults:
        - GET
        - POST
        - PUT
        - DELETE
        - PATCH
    command:
      enabled: true
      blacklist:
        # Defaults:
        - ^cache:clear$
        - ^assets:install$
    message:
      enabled: true
      flush_spans_after_handling: true
      blacklist: []
    http:
      enabled: true
      propagate_by_default: true
    doctrine:
      instrumentation: false
      propagation: false
      log_queries: false
      connections: []
  metrics:
    enabled: true
    message:
      enabled: false
    request:
      enabled: false

      # Minimum seconds between two metrics exports at the end of a request (kernel.terminate), 0 to export after every request
      flush_interval: 10
      blacklist:
        # Defaults:
        - ^/_fragment
        - ^/_profiler
        - ^/_wdt
```

## Long-running workers

Under FrankenPHP worker mode, RoadRunner or any runtime that serves many requests per process, the
batch processors would hold a request's telemetry until the batch fills or the process exits. At
`kernel.terminate` the bundle therefore flushes the tracer provider (`tracing.request.flush_spans_after_terminate`)
and the logger provider (`logging.flush_after_request`). Metrics are throttled instead, since an export
per request would be one OTLP call per request: the meter provider is flushed on the first request,
then at most once per `metrics.request.flush_interval` seconds (requires `metrics.request.enabled`).
An idle worker keeps its last metrics until its next request or its shutdown.

The tracer and logger providers are also flushed on `kernel.reset` (between two requests of a worker,
after each Messenger message and when a test kernel shuts down).

### Client disconnects

With PHP's default `ignore_user_abort=Off`, a request whose client disconnects is aborted at its next
write: no `finally` block runs and `kernel.terminate` is never dispatched, so the server span and every
span still open are never ended nor exported (and the other terminate listeners are skipped too). This
mostly bites streamed responses (`StreamedResponse`, server-sent events). For complete traces, set
`ignore_user_abort = On` in the runtime's php.ini: the request then runs to `kernel.terminate`, where
the server span is flagged `http.client_aborted=true`. Streaming code should check `connection_aborted()`
after each flush to stop producing output nobody reads.
