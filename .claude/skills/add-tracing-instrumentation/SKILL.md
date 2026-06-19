---
name: add-tracing-instrumentation
description: Add a new OpenTelemetry tracing instrumentation to this bundle (a new subsystem to trace, e.g. AI platform, a cache layer, a third-party client). Use when asked to "trace X", "add a span around X", or "instrument X" in worldia/instrumentation-bundle. Encodes the bundle's mandatory cross-cutting conventions: swappable AttributeProvider + OperationNameResolver, blacklist Voter, semconv constants, and the DI wiring across config + Configuration.php + Extension.php.
---

# Add a tracing instrumentation

Every tracing instrumentation in this bundle is built from the same five parts. Reproduce all five — reviewers reject instrumentations that skip the provider/resolver/voter abstractions or hardcode attribute names.

## Pick the seam first

- **Decorator** when the thing you trace is a service with an interface you can wrap, and the span must outlive the call (async/deferred result). Models: `src/Tracing/HttpClient/TracingHttpClient.php`, `src/Tracing/Doctrine/`. For deferred results, **end the span when the result is consumed AND add a `__destruct()` backstop that ends it if it never is** — see `TracedResponse` (`src/Tracing/HttpClient/TracedResponse.php`). A span that only ends in a downstream callback leaks.
- **EventSubscriber** when there's a Symfony event marking start/end. Models: Request, Command, Messenger (`src/Tracing/Messenger/EventListener/MessageEventSubscriber.php`).

### Get the tracer by injection, never the static facade

Inside the bundle, **inject `OpenTelemetry\API\Trace\TracerProviderInterface` via the constructor and use `TracerAwareTrait`'s `getTracer()`** (the trait declares the `$tracerProvider` property; assign the constructor arg to it). Do **not** call `Instrumentation\Tracing\Tracing::getTracer()` from bundle code — that static facade exists for _application_ code outside the bundle, where DI isn't convenient. Every in-bundle listener/decorator (e.g. `MessageEventSubscriber`, `TracingAgent`) injects the provider. For decorators built in a compiler pass, add `new Reference(TracerProviderInterface::class)` to the decorator's arguments.

## The five parts

### 1. Semantics — attributes (swappable)

Create `src/Semantics/Attribute/<Feature>AttributeProviderInterface.php` + a default impl. The impl returns `array<non-empty-string, scalar|array>` and **uses `OpenTelemetry\SemConv` constants for every attribute name** — never inline strings like `'gen_ai.system'`. Example: `src/Semantics/Attribute/MessageAttributeProvider.php` uses `MessagingIncubatingAttributes::*`. Custom keys with no semconv equivalent (e.g. `'messenger.bus'`) may be inline.

GenAI/HTTP/DB/etc. constants live in `OpenTelemetry\SemConv\TraceAttributes` (flat) and `OpenTelemetry\SemConv\Attributes\*` / `Incubating\Attributes\*` (split). Grep the installed package before inventing a name.

### 2. Semantics — span name (swappable)

If span naming has any logic, add `src/Semantics/OperationName/<Feature>OperationNameResolverInterface.php` + impl (model: `ClientRequestOperationNameResolver`). Don't hardcode the span name in the decorator/listener.

### 3. Register the semantics services

Add both interfaces to `src/DependencyInjection/config/semconv/semconv.php`, binding interface → default impl with `->set(Interface::class, Impl::class)`. This is the seam that lets users override providers by redefining the service. See lines 50–69 of that file.

### 4. Sampling / blacklist (the bundle's filtering convention)

If users should be able to exclude items, add a Voter extending `AbstractVoter` (`src/Tracing/Bridge/Sampling/Voter/`) implementing only `getOperationNameFromEvent()`, plus its interface. `AbstractVoter` does regex blacklist matching and returns `VOTE_DROP` / `VOTE_ABSTAIN`. Register the voter + a `Sampling\EventListener` in the feature's tracing config (see `config/tracing/message.php` lines 33–43).

### 5. DI wiring

- **`src/DependencyInjection/config/tracing/<feature>.php`** — register the decorator/subscriber, voter, sampling listener. Inject providers via `service(...)`, config via `param('tracing.<feature>.*')`. Use `->autoconfigure()` on subscribers.
- **`src/DependencyInjection/Configuration.php`** — add an `arrayNode('<feature>')->addDefaultsIfNotSet()` under `tracing`, with `booleanNode('enabled')` (default `true`, or `false` if it needs an optional dependency) and any `blacklist`/`methods` array nodes. See lines 121–164.
- **`src/DependencyInjection/Extension.php` → `loadTracing()`** — add `'<feature>'` to the feature loop (`foreach (['request', 'command', 'message', ...] as $feature)`), which loads `<feature>.php` when enabled and copies `blacklist`/`methods` into `tracing.<feature>.*` params. Gate optional-dependency features behind an `interface_exists()`/`class_exists()` check.

#### Choosing the decoration mechanism

This is the bundle's standing convention — follow it, don't introduce a third style:

- **Single, statically-known service id** → decorate declaratively in the `<feature>.php` config: `->decorate(Interface::class, null, <priority>)` with `service('.inner')` as the first arg. This is the _only_ case where compile-time code is not needed. Model: HttpClient (`config/tracing/http.php` decorates `HttpClientInterface`).
- **Multiple services discovered by tag** (you don't know the ids/count until the app is configured, e.g. `ai.platform`, `ai.agent`, `ai.toolbox`) → you must `findTaggedServiceIds()` and loop, which is only reliable at compile time. Put that loop in a **dedicated `*CompilerPass` class** under `src/DependencyInjection/CompilerPass/`, register an `->abstract()` template service in `<feature>.php`, and invoke the pass from `Extension::process()` gated on `$container->hasDefinition(<TemplateService>::class)`. Models: `AIPlatformTracingCompilerPass`, `AIAgentTracingCompilerPass`, `DoctrineTracingCompilerPass`.

Do **not** inline compile-time container logic directly in `Extension::process()` — all such logic lives in dedicated `*CompilerPass` classes (the Doctrine wiring used to be the lone inline exception and was extracted into `DoctrineTracingCompilerPass`).

## Verify

- No attribute-name string literals that have a semconv constant.
- Provider + resolver are interfaces registered in `semconv.php` and injected (not `new`'d).
- Deferred-span instrumentations have a destructor backstop.
- `composer test` / the relevant `tests/Tracing/<Feature>/` test passes; add an `InMemoryExporter`-based test mirroring existing ones.
