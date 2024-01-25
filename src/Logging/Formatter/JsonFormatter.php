<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Logging\Formatter;

use Monolog\Formatter\JsonFormatter as BaseJsonFormatter;
use Monolog\LogRecord;
use Monolog\Utils;

class JsonFormatter extends BaseJsonFormatter
{
    private int|null $lengthLimit = null;

    public function format(LogRecord $record): string
    {
        $normalized = $this->normalizeRecord($record);

        $json = $this->toJson($normalized, true);
        $length = \strlen($json);
        $limit = $this->getLengthLimit();

        // Truncate messages that are longer than the php.ini
        // "log_errors_max_len" configured bytes length ...
        if (0 !== $limit && $length > $limit) {
            $message = Utils::substr($this->toJson($normalized['message']), 1, -1);
            $json = str_replace($message, substr($message, 0, -$length + $this->lengthLimit - 1), $json);
        }

        return $json.($this->appendNewline ? "\n" : '');
    }

    private function getLengthLimit(): int
    {
        if (!$this->lengthLimit) {
            $this->lengthLimit = (int) \ini_get('log_errors_max_len');
        }

        return $this->lengthLimit;
    }
}
