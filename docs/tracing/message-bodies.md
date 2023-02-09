# Adding request and response bodies as span attributes when tracing HTTP requests

```php

$client = new \Instrumentation\Http\TracingHttpClient();

$client->request('GET', 'https://github.com', [
    'extra' => [
        'span_attributes' => ['request.body', 'response.body'],
    ],
]);
```
Or enable it globally setting and env var:
```ini
OTEL_PHP_HTTP_SPAN_ATTRIBUTES=request.body,response.body
```
   