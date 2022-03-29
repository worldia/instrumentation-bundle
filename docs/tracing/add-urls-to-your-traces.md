# Adding urls to your traces

```yaml
instrumentation:
  resource:
    service.name: app
  tracing:
    trace_url: http://localhost:16682/trace/{traceId}
```
