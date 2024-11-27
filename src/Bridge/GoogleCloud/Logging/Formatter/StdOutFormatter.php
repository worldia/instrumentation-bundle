<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Bridge\GoogleCloud\Logging\Formatter;

use Instrumentation\Logging\Formatter\JsonFormatter as BaseJsonFormatter;

final class StdOutFormatter extends BaseJsonFormatter
{
    public function __construct(private string $project)
    {
        parent::__construct(self::BATCH_MODE_NEWLINES, true, true);
    }

    /**
     * Translate the data into Google Cloud logging format.
     *
     * @see https://cloud.google.com/logging/docs/agent/configuration#process-payload
     * @see https://github.com/GoogleCloudPlatform/fluent-plugin-google-cloud/blob/master/lib/fluent/plugin/out_google_cloud.rb
     */
    protected function normalize(mixed $data, int $depth = 0): mixed
    {
        $data = parent::normalize($data, $depth);
        if (!\is_array($data)) {
            return $data;
        }

        if ($depth < 1) {
            // Map timestamp
            // @phpstan-ignore-next-line
            $date = new \DateTime($data['datetime']);
            $data['timestamp'] = [
                'seconds' => $date->getTimestamp(),
                'nanos' => (int) $date->format('u') * 1000,
            ];
            unset($data['datetime']);

            // Map severity
            $data['severity'] = $data['level_name'];
            unset($data['level_name']);

            // Map channel
            $data['logging.googleapis.com/labels'] = ['channel' => $data['channel']];
            unset($data['channel']);

            // Map tracing
            if (!isset($data['context']) || !\is_array($data['context'])) {
                return $data;
            }
            if (isset($data['context']['trace'])) {
                $data['logging.googleapis.com/trace'] = 'projects/'.$this->project.'/traces/'.$data['context']['trace'];
            }
            if (isset($data['context']['span'])) {
                $data['logging.googleapis.com/spanId'] = $data['context']['span'];
            }
            if (isset($data['context']['sampled'])) {
                $data['logging.googleapis.com/trace_sampled'] = $data['context']['sampled'];
            }
            if (isset($data['context']['operation'])) {
                $data['logging.googleapis.com/operation'] = $data['context']['operation'];
            }
            unset($data['context']['trace'], $data['context']['span'], $data['context']['sampled'], $data['context']['operation']);

            if ($exception = $data['context']['exception'] ?? false) {
                $data['message'] = $exception['message'];
                $data['context'] = array_merge($data['context'], $exception['context']);
                unset($data['context']['exception']);
            }
        }

        return $data;
    }

    /**
     * Translate exceptions into a format that Google Cloud Error Reporting understands.
     *
     * @see https://cloud.google.com/error-reporting/reference/rest/v1beta1/projects.events/report#reportederrorevent
     * @see https://cloud.google.com/error-reporting/reference/rest/v1beta1/ErrorContext#sourcelocation
     *
     * @return array{message:string,context:array{reportLocation:array{filePath:string,lineNumber:int,functionName:string}}}
     */
    protected function normalizeException(\Throwable $e, int $depth = 0): array
    {
        return [
            'message' => 'PHP Warning: '.(string) $e,
            'context' => [
                'reportLocation' => [
                    'filePath' => $e->getFile(),
                    'lineNumber' => $e->getLine(),
                    'functionName' => static::getFunctionNameForReport($e->getTrace()),
                ],
            ],
        ];
    }

    /**
     * This code was taken from:.
     *
     * @see https://github.com/googleapis/google-cloud-php-errorreporting/blob/master/src/Bootstrap.php#L254
     *
     * Format the function name from a stack trace. This could be a global
     * function (function_name), a class function (Class->function), or a static
     * function (Class::function).
     *
     * @param array<mixed> $trace The stack trace returned from Exception::getTrace()
     */
    private static function getFunctionNameForReport(array|null $trace = null): string
    {
        if (null === $trace) {
            return '<unknown function>';
        }
        if (empty($trace[0]['function'])) {
            return '<none>';
        }
        $functionName = [$trace[0]['function']];
        if (isset($trace[0]['type'])) {
            $functionName[] = $trace[0]['type'];
        }
        if (isset($trace[0]['class'])) {
            $functionName[] = $trace[0]['class'];
        }

        return implode('', array_reverse($functionName));
    }
}
