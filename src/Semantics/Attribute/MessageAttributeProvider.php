<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\Attribute;

use OpenTelemetry\SemConv\Incubating\Attributes\MessagingIncubatingAttributes;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpReceivedStamp;
use Symfony\Component\Messenger\Bridge\Redis\Transport\RedisReceivedStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

class MessageAttributeProvider implements MessageAttributeProviderInterface
{
    /**
     * `messaging.system.name`: {@see MessagingIncubatingAttributes::MESSAGING_SYSTEM_NAME}, named here because that
     * constant only exists from open-telemetry/sem-conv 1.45.
     */
    private const MESSAGING_SYSTEM_NAME = 'messaging.system.name';

    /**
     * @deprecated since 3.1, to be removed in 3.2: semantic conventions 1.45 renamed `messaging.system` to
     *             `messaging.system.name`. Both are emitted for one release so existing queries and dashboards
     *             keep working; move them to `messaging.system.name`.
     */
    private const MESSAGING_SYSTEM = 'messaging.system';

    public function getAttributes(Envelope $envelope): array
    {
        $attributes = [
            'messenger.message' => \get_class($envelope->getMessage()),
        ];

        $system = match (true) {
            null !== $envelope->last(RedisReceivedStamp::class) => 'redis', // @phpstan-ignore-line
            null !== $envelope->last(AmqpReceivedStamp::class) => 'rabbitmq', // @phpstan-ignore-line
            default => null,
        };
        if (null !== $system) {
            $attributes[self::MESSAGING_SYSTEM_NAME] = $system;
            $attributes[self::MESSAGING_SYSTEM] = $system;
        }

        /** @var TransportMessageIdStamp|null $stamp */
        $stamp = $envelope->last(TransportMessageIdStamp::class);
        if ($stamp) {
            $attributes[MessagingIncubatingAttributes::MESSAGING_MESSAGE_ID] = (string) $stamp->getId();
        }

        /** @var BusNameStamp|null $stamp */
        $stamp = $envelope->last(BusNameStamp::class);
        if ($stamp) {
            $attributes['messenger.bus'] = $stamp->getBusName();
        }

        return array_filter($attributes);
    }
}
