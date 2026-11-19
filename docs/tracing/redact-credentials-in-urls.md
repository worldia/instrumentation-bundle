# Redact credentials in URLs

Span URLs never carry credentials, as the OpenTelemetry semantic conventions ask:

- the userinfo of an outgoing request's URL (`https://login:secret@api.example.com/`) becomes `REDACTED:REDACTED`
  in `url.full`;
- the value of a sensitive query parameter becomes `REDACTED`, in an outgoing request's `url.full` and in an incoming
  request's `url.query`. The parameter itself is kept, so a trace still shows what was sent:
  `https://pixabay.com/api/?key=REDACTED&q=kyoto`.

Query parameter names match case-insensitively. The defaults are `access_token`, `api-key`, `api_key`, `apikey`,
`auth`, `AWSAccessKeyId`, `client_secret`, `key`, `password`, `secret`, `sig`, `signature`, `token`,
`X-Amz-Credential`, `X-Amz-Security-Token`, `X-Amz-Signature`, `X-Goog-Credential` and `X-Goog-Signature`.

## Add your own parameters

For every request, on top of the defaults:

```yaml
instrumentation:
  tracing:
    redacted_query_parameters: [appid, session]
```

For one outgoing request only, with the `redacted_query_parameters` extra option:

```php
$httpClient->request('GET', 'https://api.example.com/search', [
    'query' => ['appid' => $appId, 'q' => 'kyoto'],
    'extra' => ['redacted_query_parameters' => ['appid']],
]);
```
