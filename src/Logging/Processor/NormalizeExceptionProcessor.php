<?php

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Logging\Processor;

use Instrumentation\Semantics\Normalizer\ExceptionNormalizer;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class NormalizeExceptionProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        if (isset($record->context['exception']) && $record->context['exception'] instanceof \Throwable) {
            // @phpstan-ignore-next-line
            $record->context['exception'] = ExceptionNormalizer::normalizeException($record->context['exception']);
        }

        return $record;
    }
}
