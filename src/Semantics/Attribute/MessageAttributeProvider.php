<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\Attribute;

use OpenTelemetry\SemConv\TraceAttributes;
use OpenTelemetry\SemConv\TraceAttributeValues;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpReceivedStamp;
use Symfony\Component\Messenger\Bridge\Redis\Transport\RedisReceivedStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

class MessageAttributeProvider implements MessageAttributeProviderInterface
{
    public function getAttributes(Envelope $envelope): array
    {
        $attributes = [
            TraceAttributes::MESSAGING_DESTINATION_KIND => TraceAttributeValues::MESSAGING_DESTINATION_KIND_QUEUE,
            'messenger.message' => \get_class($envelope->getMessage()),
        ];

        if ($envelope->last(RedisReceivedStamp::class)) { // @phpstan-ignore-line
            $attributes[TraceAttributes::MESSAGING_SYSTEM] = 'redis';
        } elseif ($envelope->last(AmqpReceivedStamp::class)) { // @phpstan-ignore-line
            $attributes[TraceAttributes::MESSAGING_SYSTEM] = 'rabbitmq';
            $attributes[TraceAttributes::MESSAGING_PROTOCOL] = 'AMQP';
        }

        /** @var TransportMessageIdStamp|null $stamp */
        $stamp = $envelope->last(TransportMessageIdStamp::class);
        if ($stamp) {
            $attributes[TraceAttributes::MESSAGING_MESSAGE_ID] = (string) $stamp->getId();
        }

        /** @var BusNameStamp|null $stamp */
        $stamp = $envelope->last(BusNameStamp::class);
        if ($stamp) {
            $attributes['messenger.bus'] = $stamp->getBusName();
        }

        return array_filter($attributes);
    }
}
