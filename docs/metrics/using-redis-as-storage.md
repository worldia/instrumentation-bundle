# Using Redis as metrics storage

```yaml
instrumentation:
  resource:
    service.name: app
  metrics:
    storage:
      adapter: redis
      instance: 'my_metrics_redis_service'

services:
  my_metrics_redis_service:
    class: Redis
    calls:
      - connect: ['redis', 6379]
```
