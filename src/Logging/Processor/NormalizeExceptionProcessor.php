<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Logging\Processor;

use Instrumentation\Semantics\Normalizer\ExceptionNormalizer;

class NormalizeExceptionProcessor
{
    /**
     * @param array<mixed> $record
     *
     * @return array<mixed>
     */
    public function __invoke(array $record): array
    {
        if (isset($record['context']['exception']) && $record['context']['exception'] instanceof \Throwable) {
            $record['context']['exception'] = ExceptionNormalizer::normalizeException($record['context']['exception']);
        }

        return $record;
    }
}
