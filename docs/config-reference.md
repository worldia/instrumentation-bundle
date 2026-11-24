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

    # Query parameters whose values are redacted from span URLs (outgoing `url.full`, incoming `url.query`),
    # case-insensitively, on top of the defaults (key, api_key, token, sig, X-Goog-Signature...).
    # Per outgoing request: the `redacted_query_parameters` extra option.
    redacted_query_parameters: []

      # Examples:
      # - appid
      # - session
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
      # The shortest statement (ms) that is a span of its own, 0: every statement is one. Every statement is counted onto the
      # request, command or message either way: db.statements.count, db.statements.total_ms, db.statements.spans.
      min_duration_ms: 0
      connections: []
  # Continuous profiling of requests (Excimer sampling, pushed to Pyroscope): one profile per sampled request, linked to its trace. Needs ext-excimer; a profile is only taken while `active` and the extension is loaded.
  profiling:

    # Registers the profiler. Static (read when the container is built): turn it on everywhere and switch profiling per environment with `active`.
    enabled: false

    # Runtime switch, usually an environment variable, so one image profiles where it is told to.
    active: true # Example: '%env(bool:PROFILING_ENABLED)%'

    # Samples per second inside a profiled request. 20 to 100 showed no measurable overhead.
    frequency: 50

    # Share of requests profiled, 0 to 1. 0 profiles only the requests carrying the on-demand token.
    request_rate: 1.0

    # `wall` answers where the time went (waiting on a database counts), `cpu` only the time the thread ran.
    clock: wall # One of "wall"; "cpu"

    # Deepest stack kept.
    max_depth: 256
    on_demand:

      # A request carrying this secret in `header` is always profiled. Empty: off. Keep it a secret (an environment variable).
      token: ''
      header: X-Profile-Token

      # A request carrying the token also has its SQL statements captured as events of its main span (`db.statement`: text, duration, database; at most 100): which statements a page runs and which cost the most, with no span per statement. Prepared text only, the literals of a statement run as it is masked. Needs tracing.doctrine. Off by default: a statement is not data to put in a trace unasked.
      statements: false

    # The attribute set on the main span with the id of its profile: what makes Grafana show "Profiles for this span". Empty: not set.
    span_attribute: pyroscope.profile.id
    pyroscope:

      # Base URL of the Pyroscope (or of an Alloy pyroscope.receive_http). Empty: profiles are taken and dropped.
      url: '' # Example: 'http://pyroscope:4040'

      # The series name (the service_name label).
      application: '%service.name%'

      # The `env` label. Empty: none.
      environment: ''

      # How long the body of a profile may take to go out (name lookup, connect, upload) after the response; the answer is never waited for.
      upload_deadline_ms: 150

      # Uploads pending at once; past that a profile is dropped.
      max_in_flight: 8
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

The Doctrine instrumentation follows the same rule: a connection lives as long as the worker, but the `db.orm`
span its query spans hang under, and the sqlcommenter `traceparent` it propagates, are rebuilt for each request,
command or message (each main span), so a query always belongs to the trace of the work that ran it.

### Client disconnects

With PHP's default `ignore_user_abort=Off`, a request whose client disconnects is aborted at its next
write: no `finally` block runs and `kernel.terminate` is never dispatched, so the server span and every
span still open are never ended nor exported (and the other terminate listeners are skipped too). This
mostly bites streamed responses (`StreamedResponse`, server-sent events). For complete traces, set
`ignore_user_abort = On` in the runtime's php.ini: the request then runs to `kernel.terminate`, where
the server span is flagged `http.client_aborted=true`. Streaming code should check `connection_aborted()`
after each flush to stop producing output nobody reads.
