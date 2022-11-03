<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\OperationName;

use OpenTelemetry\SemConv\TraceAttributeValues;
use Symfony\Component\Messenger\Envelope;

interface MessageOperationNameResolverInterface
{
    /**
     * @see https://github.com/open-telemetry/opentelemetry-specification/blob/v1.9.0/specification/trace/semantic_conventions/messaging.md#operation-names
     *
     * @param TraceAttributeValues::MESSAGING_OPERATION_* $operation One of 'send', 'receive' or 'process'
     *
     * @return string&non-empty-string
     */
    public function getOperationName(Envelope $envelope, string $operation): string;
}
