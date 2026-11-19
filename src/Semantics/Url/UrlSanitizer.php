<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\Url;

/**
 * Redacts credentials from a URL before it becomes a span attribute, as the OpenTelemetry semantic conventions ask
 * for `url.full` and `url.query`: the userinfo of `https://user:secret@host/` becomes `REDACTED:REDACTED`, and the
 * value of each sensitive query parameter becomes `REDACTED` (the parameter itself is kept, so a trace still shows
 * what was sent). Parameter names are matched case-insensitively; everything else is left byte for byte.
 */
final class UrlSanitizer
{
    public const REDACTED = 'REDACTED';

    /**
     * The semantic conventions' defaults (`AWSAccessKeyId`, `Signature`, `sig`, `X-Goog-Signature`) plus the names
     * APIs commonly take a key or a token under.
     */
    public const DEFAULT_QUERY_PARAMETERS = [
        'access_token',
        'api-key',
        'api_key',
        'apikey',
        'auth',
        'AWSAccessKeyId',
        'client_secret',
        'key',
        'password',
        'secret',
        'sig',
        'signature',
        'token',
        'X-Amz-Credential',
        'X-Amz-Security-Token',
        'X-Amz-Signature',
        'X-Goog-Credential',
        'X-Goog-Signature',
    ];

    /** @var array<string, true> lowercased names */
    private readonly array $parameters;

    /**
     * @param list<string> $queryParameters the query parameters whose values are redacted
     */
    public function __construct(array $queryParameters = self::DEFAULT_QUERY_PARAMETERS)
    {
        $this->parameters = self::index($queryParameters);
    }

    /**
     * @param list<string> $extraQueryParameters redacted too, for this URL only
     */
    public function sanitize(string $url, array $extraQueryParameters = []): string
    {
        // The userinfo is whatever precedes an `@` within the authority: never past the first `/`, `?` or `#`.
        $url = preg_replace('~^([a-z][a-z0-9+.\-]*://)[^/?#@]*@~i', '$1'.self::REDACTED.':'.self::REDACTED.'@', $url) ?? $url;

        $queryStart = strpos($url, '?');
        if (false === $queryStart) {
            return $url;
        }
        $fragmentStart = strpos($url, '#', $queryStart);
        $query = false === $fragmentStart ? substr($url, $queryStart + 1) : substr($url, $queryStart + 1, $fragmentStart - $queryStart - 1);

        return substr($url, 0, $queryStart + 1).$this->sanitizeQuery($query, $extraQueryParameters).(false === $fragmentStart ? '' : substr($url, $fragmentStart));
    }

    /**
     * A query string (without its `?`), its sensitive values redacted.
     *
     * @param list<string> $extraQueryParameters redacted too, for this query only
     */
    public function sanitizeQuery(string $query, array $extraQueryParameters = []): string
    {
        if ('' === $query) {
            return $query;
        }
        $parameters = [] === $extraQueryParameters ? $this->parameters : $this->parameters + self::index($extraQueryParameters);

        $pairs = explode('&', $query);
        foreach ($pairs as $i => $pair) {
            $separator = strpos($pair, '=');
            if (false === $separator) {
                continue;
            }
            $name = strtolower(urldecode(substr($pair, 0, $separator)));
            if (isset($parameters[$name])) {
                $pairs[$i] = substr($pair, 0, $separator + 1).self::REDACTED;
            }
        }

        return implode('&', $pairs);
    }

    /**
     * @param list<string> $names
     *
     * @return array<string, true>
     */
    private static function index(array $names): array
    {
        $index = [];
        foreach ($names as $name) {
            $index[strtolower($name)] = true;
        }

        return $index;
    }
}
