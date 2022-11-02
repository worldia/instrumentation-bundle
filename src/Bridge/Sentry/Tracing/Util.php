<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Bridge\Sentry\Tracing;

use Monolog\Logger;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Common\Time\ClockInterface;
use Psr\Log\LogLevel;

final class Util
{
    private const SEVERITY_DEBUG = 'debug';
    private const SEVERITY_INFO = 'info';
    private const SEVERITY_WARNING = 'warning';
    private const SEVERITY_ERROR = 'error';
    private const SEVERITY_FATAL = 'fatal';

    private const SPAN_STATUS_OK = 'ok';
    private const SPAN_STATUS_UNKNOWN_ERROR = 'unknown_error';

    public static function toSentrySeverity(int|string $severity): string
    {
        if (\is_string($severity)) {
            $severity = strtolower($severity);
        }

        return match ($severity) {
            LogLevel::DEBUG => self::SEVERITY_DEBUG,
            Logger::DEBUG => self::SEVERITY_DEBUG,
            LogLevel::INFO => self::SEVERITY_INFO,
            Logger::INFO => self::SEVERITY_INFO,
            LogLevel::NOTICE => self::SEVERITY_INFO,
            Logger::NOTICE => self::SEVERITY_INFO,
            LogLevel::WARNING => self::SEVERITY_WARNING,
            Logger::WARNING => self::SEVERITY_WARNING,
            LogLevel::ERROR => self::SEVERITY_ERROR,
            Logger::ERROR => self::SEVERITY_ERROR,
            LogLevel::EMERGENCY => self::SEVERITY_FATAL,
            Logger::EMERGENCY => self::SEVERITY_FATAL,
            LogLevel::ALERT => self::SEVERITY_FATAL,
            Logger::ALERT => self::SEVERITY_FATAL,
            LogLevel::CRITICAL => self::SEVERITY_FATAL,
            Logger::CRITICAL => self::SEVERITY_FATAL,
            default => throw new \InvalidArgumentException(sprintf('Unknown severity: "%s"', $severity))
        };
    }

    public static function toSentrySpanStatus(string $status): ?string
    {
        return match ($status) {
            StatusCode::STATUS_OK => self::SPAN_STATUS_OK,
            StatusCode::STATUS_ERROR => self::SPAN_STATUS_UNKNOWN_ERROR,
            StatusCode::STATUS_UNSET => self::SPAN_STATUS_OK,
            default => self::SPAN_STATUS_OK
        };
    }

    public static function nanosToSeconds(int $nanos): float
    {
        return $nanos / ClockInterface::NANOS_PER_SECOND;
    }
}
