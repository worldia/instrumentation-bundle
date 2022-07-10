<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Logging\Processor;

use Instrumentation\Semantics\Normalizer\ExceptionNormalizer;
use Monolog\LogRecord;

class NormalizeExceptionProcessor
{
    public function __invoke(LogRecord $record): LogRecord
    {
        if (isset($record->context['exception']) && $record->context['exception'] instanceof \Throwable) {
            $context = $record->context;
            $context['exception'] = ExceptionNormalizer::normalizeException($record->context['exception']);

            $record = $record->with(
                context: $context,
            );
        }

        return $record;
    }
}
