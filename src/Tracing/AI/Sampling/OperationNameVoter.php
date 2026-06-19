<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\AI\Sampling;

/**
 * Decides whether an AI operation should be traced, based on a regex blacklist
 * matched against an identifier (the model, agent name or tool name).
 *
 * AI tracing is decorator-based rather than event-based, so it cannot use the
 * event/sampler Voter machinery in Tracing\Bridge\Sampling; the decorator gates
 * span creation on this voter directly. The blacklist matching mirrors
 * {@see \Instrumentation\Tracing\Bridge\Sampling\Voter\AbstractVoter}.
 */
final class OperationNameVoter
{
    /**
     * @param array<string> $blacklist
     */
    public function __construct(private readonly array $blacklist)
    {
    }

    public function shouldTrace(string $name): bool
    {
        foreach ($this->blacklist as $pattern) {
            if (1 === preg_match("|$pattern|", $name)) {
                return false;
            }
        }

        return true;
    }
}
