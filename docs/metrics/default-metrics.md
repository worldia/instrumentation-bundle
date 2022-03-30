# Default metrics

### Requests
Ref. [RequestEventSubscriber](../../src/Metrics/EventSubscriber/RequestEventSubscriber.php).

- `requests_handled_total` (`counter`): Total requests handled by this instance
- `requests_handling` (`gauge`): Number of requests this instance is currently handling
- `response_codes_total` (`counter`): Number of requests per status code (labels: `code`)
- `response_times_seconds` (`histogram`): Distribution of response times in seconds

### Messages
Ref. [MessageEventSubscriber](../../src/Metrics/EventSubscriber/MessageEventSubscriber.php).

- `messages_handling` (`gauge`): Number of messages this instance is currently handling (labels: `bus`, `class`)
- `messages_handled_total` (`counter`): Number of messages handled successfully (labels: `bus`, `class`)
- `messages_failed_total` (`counter`): Number of messages handled with failure (labels: `bus`, `class`)

### Consumers
Ref. [ConsumerEventSubscriber](../../src/Metrics/EventSubscriber/ConsumerEventSubscriber.php).

- `consumers_active` (`gauge`): Number of active consumers (labels: `queue`)

### Health
Ref. [Health Endpoint](../../src/Health/Controller/Endpoint.php). 

- `app_health` (`gauge`): Global application health. `0`: unhealthy, `1`: degraded, `2`: healthy
