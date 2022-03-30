# Customizing keys of trace context properties

```yaml
instrumentation:
  logging:
    keys:
      trace: logging\.googleapis\.com/trace
      span: logging\.googleapis\.com/spanId
      sampled: logging\.googleapis\.com/trace_sampled
```

Example log:

```json
{
   "message":"Matched route \"_metrics\".",
   "context":{
      "route":"_metrics",
      "route_parameters":{
         "_route":"_metrics",
         "_controller":"Instrumentation\\Metrics\\Controller\\Endpoint"
      },
      "request_uri":"http://nginx/metrics",
      "method":"GET"
   },
   "level":200,
   "level_name":"INFO",
   "channel":"request",
   "datetime":"2022-03-30T11:00:11.970417+00:00",
   "logging.googleapis.com/trace":"80c9ff9cefbcc4e568c83b02d212deaf",
   "logging.googleapis.com/spanId":"c2819505c397bdb4",
   "logging.googleapis.com/trace_sampled":false
}
```
