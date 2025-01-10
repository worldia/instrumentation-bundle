FROM caddy:2.8-builder AS builder

RUN set -e; xcaddy build \
	--with github.com/caddyserver/transform-encoder \
	--with github.com/cdaguerre/caddy-otel-span-name@fa3a491654ca13ae0cc9ddf86bdff009ec22a0a1 \
	--with github.com/caddyserver/cache-handler@v0.15.0 \
	;

FROM caddy:2.8 AS caddy

COPY --from=builder /usr/bin/caddy /usr/bin/caddy
COPY ./Caddyfile /etc/caddy/Caddyfile