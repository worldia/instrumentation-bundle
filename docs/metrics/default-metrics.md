# Default metrics

### Requests
Ref. [RequestEventSubscriber](../../src/Metrics/EventSubscriber/RequestEventSubscriber.php).

| Name                     | Type        | Description                                            | Labels              |
|--------------------------|-------------|--------------------------------------------------------|---------------------|
| `requests_handled_total` | `counter`   | Total requests handled by this instance                |                     |
| `requests_handling`      | `gauge`     | Number of requests this instance is currently handling |                     |
| `response_codes_total`   | `counter`   | Number of requests per status code and operation       | `code`, `operation` |
| `response_times_seconds` | `histogram` | Distribution of response times in seconds              |                     |

### Messages
Ref. [MessageEventSubscriber](../../src/Metrics/EventSubscriber/MessageEventSubscriber.php).

| Name                     | Type      | Description                                            | Labels         |
|--------------------------|-----------|--------------------------------------------------------|----------------|
| `messages_handling`      | `gauge`   | Number of messages this instance is currently handling | `bus`, `class` |
| `messages_handled_total` | `counter` | Number of messages handled successfully                | `bus`, `class` |
| `messages_failed_total`  | `counter` | Number of messages handled with failure                | `bus`, `class` |

### Consumers
Ref. [ConsumerEventSubscriber](../../src/Metrics/EventSubscriber/ConsumerEventSubscriber.php).

| Name               | Type    | Description                | Labels  |
|--------------------|---------|----------------------------|---------|
| `consumers_active` | `gauge` | Number of active consumers | `queue` |

### Health
Ref. [Health Endpoint](../../src/Health/Controller/Endpoint.php). 

| Name         | Type    | Description                                                            | Labels |
|--------------|---------|------------------------------------------------------------------------|--------|
| `app_health` | `gauge` | Global application health. `0`: unhealthy, `1`: degraded, `2`: healthy |        |
