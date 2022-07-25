<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Metrics\Storage;

class RedisPrefixProvider
{
    protected static ?RedisPrefixProvider $instance = null;
    protected string $prefix;

    protected function __construct()
    {
        if (false === $hostname = gethostname()) {
            throw new \RuntimeException('Impossible to retrieve the hostname.');
        }

        $this->prefix = "metrics:$hostname";
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    public static function getInstance(): self
    {
        if (null === static::$instance) {
            static::$instance = new self();
        }

        return static::$instance;
    }
}
