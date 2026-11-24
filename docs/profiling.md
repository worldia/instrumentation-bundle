# Profiling requests

Traces say which span is slow, not which PHP code runs inside it. With `instrumentation.profiling` the bundle samples the stacks of a
request with [Excimer](https://www.mediawiki.org/wiki/Excimer), sends the profile to [Pyroscope](https://grafana.com/oss/pyroscope/),
and tags the request's main span so Grafana shows **Profiles for this span** on the trace and opens that request's flame graph.

OpenTelemetry's own profiles signal is still experimental and has no PHP SDK: the trace correlation is OpenTelemetry (the span id),
the profile itself is a pprof pushed to Pyroscope. The shipper is an interface (`ProfileShipperInterface`): an OTLP-profiles shipper
can replace the Pyroscope one without touching the profiler.

## Setup

```sh
install-php-extensions excimer   # 1.2.6 or later: the first release that repairs ZTS mode (FrankenPHP)
```

```yaml
instrumentation:
  profiling:
    enabled: true                              # registers the profiler: static, on everywhere
    active: '%env(bool:PROFILING_ENABLED)%'    # runtime: one image profiles where it is told to
    request_rate: '%env(float:PROFILING_REQUEST_RATE)%'   # 0 = only the requests carrying the on-demand token
    on_demand:
      token: '%env(PROFILING_ON_DEMAND_TOKEN)%'   # or, shared with the other on-demand features, `instrumentation.on_demand.token`
    pyroscope:
      url: '%env(PYROSCOPE_URL)%'
      environment: '%env(DEPLOYMENT_ENVIRONMENT)%'
```

Give every environment variable a default (`env(PROFILING_ENABLED): '0'` in `parameters`): an unset variable with no default fails the
container. Grafana needs the Tempo datasource to link spans to the Pyroscope one (`tracesToProfiles` with the profile type
`wall:wall:nanoseconds:wall:nanoseconds`; the span attribute is `pyroscope.profile.id`).

## Which requests are profiled

None unless `active` and the extension is loaded. Then a share of them (`request_rate`, 0 to 1), and always the one carrying the
on-demand token in the `X-Profile-Token` header (`on_demand.header`): with a rate of 0 that is the only profiled traffic, at no cost
to the rest. Profile one request on demand with `curl -H 'X-Profile-Token: ...' -H 'Cache-Control: no-cache' ...` (a response served
by an HTTP cache runs no PHP) and open its trace.

### The statements of that request

The request that carries the token is the one the shared on-demand trigger (`instrumentation.on_demand`: the token and its header, which
`profiling.on_demand` also set, for compatibility) says an operator asked for. Profiling is one thing it switches on; Doctrine tracing's
`tracing.doctrine.statements.on_demand: true` is another: that request also has its SQL statements captured, as events of its main span
(`db.statement`, in order: `db.statement.text`, `db.statement.duration_ms`, `db.namespace`, `db.statement.index`), whether or not it is
profiled and with or without Excimer. A trace of that one request then says which statements a page runs and which cost the most (an
N+1, a statement to merge or lighten), while the trace of any other request keeps what `tracing.doctrine.min_duration_ms` leaves of it:
the counts and totals on the main span, and a span for the slow statements. At most `tracing.doctrine.statements.max` (100) are captured
(`db.statements.capture_dropped` on the main span counts the rest; the span's other events count against the SDK's limit of 128). Quoted
literals are masked in every statement (`'...'`, `E'...'`, `$$...$$`, a quote never closed), and the numbers of a statement run as it is
(`Connection::query()` or `exec()`) too; a prepared statement keeps its placeholders and its numbers. A statement of more than 64 KB is
not kept (only its size), and the comment the trace propagation adds is dropped. It is the SQL the application wrote: for the one
request an operator asked for, never traffic. It needs a request the tracer samples (the main span recording). Off by default.

## What a profile is

A pprof with one sample type, `wall` (or `cpu`) in nanoseconds: an Excimer event is one sampling period of the real-time clock, so
time spent waiting on a database counts. Every sample has the label `span_id` (the main span), which is what Grafana's span profile
query filters on; the series has the labels of the `ProfileLabelProviderInterface` services (`route` and `method` by default). Implement
that interface (autoconfigured) to add the channel, the tenant, ...: keep the values few, a backend keeps one series per label set.

The timer starts at `kernel.request` and stops at `kernel.terminate`, before the span export is flushed, so the profile does not contain
the export. `kernel.reset` stops a timer whose request never terminated. Excimer samples at the next VM interrupt: a call blocked in C
(a query, `curl`) is charged to the PHP function that made it, once it returns.

## Shipping, and why it never waits

`PyroscopeShipper` posts to `/ingest` (`format=pprof`). Pyroscope 2.x acknowledges a write only when its segment is flushed, 8 to 500 ms
later: a synchronous POST after the response would hold a worker thread that long per profiled request, and FrankenPHP answers a busy
pool by starting more worker threads (+180-270 MB each). So the upload runs on a curl multi handle, driven until the whole body is on the
wire (at most `upload_deadline_ms`; the name lookup and the connect are most of it in a cluster) and the answer is read by the next
request. A fixed few milliseconds was enough locally and not in a cluster, where the transfer then never finished on a worker profiled
rarely. At most `max_in_flight` uploads are pending, a profile is dropped past that; failures are warnings, never exceptions.

Measured on a 170 ms request: no visible latency or memory change at 20, 50 and 100 Hz.
