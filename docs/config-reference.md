# Configuration reference

As output by ```bin/console config:dump-reference instrumentation```.

```yaml
instrumentation:

    # Use semantic tags defined in the OpenTelemetry specification (https://github.com/open-telemetry/opentelemetry-specification/blob/main/specification/resource/semantic_conventions/README.md)
    resource:

        # Default:
        service.name:        app

        # Examples:
        service.name:        my-instrumented-app
        service.version:     1.2.3
    baggage:
        enabled:              true
    health:
        enabled:              true
        path:                 /_healthz
    logging:
        enabled:              true
        keys:
            trace:                context.trace
            span:                 context.span
            sampled:              context.sampled
            operation:            context.operation
    tracing:
        enabled:              true

        # Accepts any DSN handled by OpenTelemetry's ExporterFactory. See: https://github.com/open-telemetry/opentelemetry-php/blob/main/src/SDK/Trace/ExporterFactory.php
        dsn:                  '%env(TRACER_URL)%' # Example: 'zipkin+http://jaeger:9411/api/v2/spans'

        # Allows you to have links to your traces generated in error messages and twig.
        trace_url:            ~ # Example: 'http://localhost:16682/trace/{traceId}'
        logs:

            # One of Monolog\Logger the levels.
            level:                200 # One of 100; 200; 250; 300; 400; 500; 550; 600
            channels:             []
        request:
            enabled:              true
            incoming_header:
                name:                 ~
                regex:                ~
            blacklist:

                # Defaults:
                - ^/_fragment
                - ^/_profiler
        command:
            enabled:              true
            blacklist:

                # Defaults:
                - ^cache:clear$
                - ^assets:install$
        message:
            enabled:              true
            blacklist:            []
    metrics:
        enabled:              true
        path:                 /metrics

        # Prefix added to all metrics.
        namespace:            ''
        storage:
            adapter:              apcu # One of "apc"; "apcu"; "redis"; "in_memory"

            # When using the redis adapter, set "instance" to a service id that is an instance of \Redis
            instance:             null
        metrics:

            # Prototype
            -
                name:                 ~ # Required
                help:                 ~ # Required
                type:                 ~ # One of "gauge"; "counter"; "histogram", Required
                labels:               []
                buckets:              []
```
