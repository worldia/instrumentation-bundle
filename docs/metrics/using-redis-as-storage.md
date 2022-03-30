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

  # OR

  my_metrics_redis_service:
    class: Redis
    factory: [Symfony\Component\Cache\Traits\RedisTrait, 'createConnection'] # Requires the Symfony cache component
    arguments:
      $dsn: redis://my-redis-instance:6379
```
