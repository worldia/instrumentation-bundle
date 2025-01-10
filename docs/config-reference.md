# Configuration reference

As output by ```bin/console config:dump-reference instrumentation```.

```yaml
# Default configuration for extension with alias: "instrumentation"
instrumentation:

    # Use semantic tags defined in the OpenTelemetry specification (https://github.com/open-telemetry/opentelemetry-specification/blob/main/specification/resource/semantic_conventions/README.md)
    resource:

        # Default:
        service.name:        %env(default:instrumentation.default_service_name:OTEL_SERVICE_NAME)%

        # Examples:
        # service.name:        my_instrumented_app
        # service.version:     1.2.3
    baggage:
        enabled:              false
    logging:
        enabled:              true
    tracing:
        enabled:              true

        # Allows you to have links to your traces generated in error messages and twig.
        trace_url:            ~ # Example: 'http://localhost:16682/trace/{traceId}'
        request:
            enabled:              true
            attributes:

                # Use the primary server name of the matched virtual host
                server_name:          null # Example: example.com
                headers:              []

                    # Examples:
                    # - accept
                    # - accept-encoding
            blacklist:

                # Defaults:
                - ^/_fragment
                - ^/_profiler
                - ^/_wdt
            methods:

                # Defaults:
                - GET
                - POST
                - PUT
                - DELETE
                - PATCH
        command:
            enabled:              true
            blacklist:

                # Defaults:
                - ^cache:clear$
                - ^assets:install$
        message:
            enabled:              true
            flush_spans_after_handling: true
            blacklist:            []
        http:
            enabled:              true
            propagate_by_default: true
        doctrine:
            instrumentation:      false
            propagation:          false
            log_queries:          false
            connections:          []
    metrics:
        enabled:              true
        message:
            enabled:              false
        request:
            enabled:              false
            blacklist:

                # Defaults:
                - ^/_fragment
                - ^/_profiler
                - ^/_wdt

```
