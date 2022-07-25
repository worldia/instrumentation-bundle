<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace spec\Instrumentation\Metrics\Storage;

use PhpSpec\ObjectBehavior;

class RedisPrefixProviderSpec extends ObjectBehavior
{
    public function it_provides_the_metrics_prefix_for_redis_storage(): void
    {
        $this->beConstructedThrough('getInstance');

        $this->prefix()->shouldBe('metrics:'.gethostname());
    }
}
