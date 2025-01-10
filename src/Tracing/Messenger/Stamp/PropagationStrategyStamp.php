<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Messenger\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

final class PropagationStrategyStamp implements StampInterface
{
    public const STRATEGY_LINK = 'link';
    public const STRATEGY_PARENT = 'parent';

    public function __construct(private string $strategy)
    {
        if (!\in_array($strategy, [self::STRATEGY_LINK, self::STRATEGY_PARENT])) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a valid value for strategy', $strategy));
        }
    }

    public function getStrategy(): string
    {
        return $this->strategy;
    }
}
