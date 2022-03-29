<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Propagation;

use Symfony\Component\HttpFoundation\Request;

class RegexIncomingTraceHeaderResolver implements IncomingTraceHeaderResolverInterface
{
    public function __construct(private ?string $headerName = null, private ?string $regex = null)
    {
    }

    public function getTraceId(Request $request): ?string
    {
        $matches = $this->resolve($request);

        return $matches['trace'] ?? null;
    }

    public function getSpanId(Request $request): ?string
    {
        $matches = $this->resolve($request);

        return $matches['spanId'] ?? null;
    }

    public function isSampled(Request $request): ?bool
    {
        $matches = $this->resolve($request);

        return $matches['sampled'] ?? null;
    }

    /**
     * @param Request $request [description]
     *
     * @return array{trace?:string,spanId?:string,sampled?:bool}
     */
    private function resolve(Request $request): array
    {
        if (null === $this->headerName || null === $this->regex) {
            return [];
        }

        $value = (string) $request->headers->get($this->headerName);

        preg_match($this->regex, $value, $matches);

        if (!isset($matches['trace'])) {
            return [];
        }

        return array_filter([
            'trace' => str_pad($matches['trace'], 32, '0', \STR_PAD_LEFT),
            'spanId' => isset($matches['span']) ? substr(str_pad($matches['span'], 16, '0', \STR_PAD_LEFT), 0, 16) : null,
            'sampled' => isset($matches['sampled']) ? (bool) filter_var($matches['sampled'], \FILTER_VALIDATE_BOOLEAN) : null,
        ]);
    }
}
