# Adding urls to your traces

```
instrumentation:
  resource:
    service.name: app
  tracing:
    trace_url: http://localhost:16682/trace/{traceId}
```
