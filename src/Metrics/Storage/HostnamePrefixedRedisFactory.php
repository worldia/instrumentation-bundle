<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Metrics\Storage;

use Prometheus\Storage\Redis as RedisAdapter;
use Redis as RedisConnection;

final class HostnamePrefixedRedisFactory
{
    public function __construct(private RedisPrefixProvider|null $redisPrefixProvider)
    {
    }

    public function createFromExistingConnection(RedisConnection $connection): RedisAdapter
    {
        $adapter = RedisAdapter::fromExistingConnection($connection);

        if (null !== $this->redisPrefixProvider) {
            $adapter->setPrefix($this->redisPrefixProvider->prefix());
        }

        return $adapter;
    }
}
