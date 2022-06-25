<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Propagation\Exception;

final class ContextPropagationException extends \RuntimeException
{
    public static function becauseNoParentTrace(): self
    {
        return new self(
            'The context could not be propagated because no parent trace was found.'
            .' Make sure to activate a span before trying to propagate the context.'
        );
    }
}
