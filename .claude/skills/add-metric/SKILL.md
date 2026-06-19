---
name: add-metric
description: Add a new OpenTelemetry metric (counter, up-down counter, histogram, or gauge) to this bundle. Use when asked to "add a metric", "count X", "record a histogram for X", or "publish token/usage metrics" in worldia/instrumentation-bundle. Encodes the metrics conventions, which differ from tracing — DI-only meter (no static facade), hardcoded attribute strings, and a separate feature toggle/subscriber.
---

# Add a metric

Metrics in this bundle are wired separately from tracing and follow different rules. Do not bolt metrics onto a tracing decorator/subscriber — even when both observe the same event, the bundle keeps them as independent subscribers under independent toggles (see Messenger: `src/Tracing/Messenger/...` vs `src/Metrics/EventSubscriber/MessageEventSubscriber.php`).

## Key differences from tracing

- **No static facade.** There is no `Metrics::getMeter()` equivalent to `Tracing::getTracer()`. Inject `OpenTelemetry\API\Metrics\MeterProviderInterface` and call `$meterProvider->getMeter('instrumentation')` in the constructor.
- **Attribute names are plain strings** (e.g. `['bus' => ..., 'class' => ...]`), not `OpenTelemetry\SemConv` constants. (Tracing uses semconv constants; metrics historically don't.) If a published semconv _metric_ exists for your case (e.g. `gen_ai.client.token.usage`), prefer that instrument name and its semconv attributes for ecosystem compatibility, but match the surrounding code's style.

## Instrument types & naming

Create instruments on the meter and act on them:

- `createCounter('<name>_total')->add($n, $attrs)` — monotonic.
- `createUpDownCounter('<name>')->add(±1, $attrs)` — gauge-like running count (e.g. `consumers_active`, `messages_handling`).
- `createHistogram('<name>_<unit>', '<unit>', '<desc>')->record($v, $attrs)` — distributions (e.g. `messages_time_to_consume_seconds`).
- `createGauge('<name>_<unit>', null, '<desc>')->record($v, $attrs)` — point-in-time (e.g. `memory_usage_bytes`).

Naming: snake_case, unit suffix (`_seconds`, `_bytes`), `_total` suffix for monotonic counters. Reference: `src/Metrics/EventSubscriber/MessageEventSubscriber.php`, `ConsumerEventSubscriber.php`, `RequestEventSubscriber.php`.

## The subscriber

Add `src/Metrics/EventSubscriber/<Feature>EventSubscriber.php` implementing `EventSubscriberInterface`, with `MeterProviderInterface` injected. Cache the meter in the constructor; create instruments lazily where recorded (the existing code calls `createCounter(...)` at the record site — that's fine, instruments are idempotent by name).

## DI wiring

- **`src/DependencyInjection/config/metrics/<feature>.php`** — register the subscriber with `->autoconfigure()` and `->args([service(MeterProviderInterface::class)])`. Model: `config/metrics/message.php`.
- **`src/DependencyInjection/Configuration.php`** — add `arrayNode('<feature>')->addDefaultsIfNotSet()` under `metrics`, with `booleanNode('enabled')->defaultFalse()` and any `blacklist` node (see the `metrics` tree, lines 180–205).
- **`src/DependencyInjection/Extension.php` → `loadMetrics()`** — add `if ($this->isConfigEnabled($container, $config['<feature>'])) { ...; $loader->load('<feature>.php'); }`. Set `metrics.<feature>.blacklist` param first if the subscriber filters (model: the `request` branch, lines 230–233). The core `metrics.php` (MeterProvider) is always loaded.

## Verify

- Subscriber gets the meter from injected `MeterProviderInterface`, never a static accessor.
- Instrument name follows snake_case + unit/`_total` convention.
- Feature is opt-in via its own `metrics.<feature>.enabled` node and loaded in `loadMetrics()`.
- Add/extend a test under `tests/Metrics/` using an in-memory metric reader, mirroring existing metric tests.
