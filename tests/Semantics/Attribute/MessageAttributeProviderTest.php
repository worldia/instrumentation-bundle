<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Semantics\Attribute;

use Instrumentation\Semantics\Attribute\MessageAttributeProvider;
use Instrumentation\Semantics\Attribute\MessageAttributeProviderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpReceivedStamp;
use Symfony\Component\Messenger\Bridge\Redis\Transport\RedisReceivedStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

class MessageAttributeProviderTest extends TestCase
{
    public function testItImplementsMessageAttributeProviderInterface(): void
    {
        $this->assertTrue(is_a(MessageAttributeProvider::class, MessageAttributeProviderInterface::class, true));
    }

    public function testItSetsMessageClass(): void
    {
        $message = new \stdClass();
        $envelope = new Envelope($message);

        $provider = new MessageAttributeProvider();
        $attributes = $provider->getAttributes($envelope);

        $this->assertEquals('stdClass', $attributes['messenger.message']);
    }

    public function testItSetsBusName(): void
    {
        $message = new \stdClass();
        $envelope = new Envelope($message, [new BusNameStamp('some-bus')]);

        $provider = new MessageAttributeProvider();
        $attributes = $provider->getAttributes($envelope);

        $this->assertEquals('some-bus', $attributes['messenger.bus']);
    }

    public function testItSetsMessageId(): void
    {
        $message = new \stdClass();
        $envelope = new Envelope($message, [new TransportMessageIdStamp('some-id')]);

        $provider = new MessageAttributeProvider();
        $attributes = $provider->getAttributes($envelope);

        $this->assertEquals('some-id', $attributes['messaging.message.id']);
    }

    public function testItSetsMessagingSystemIfRedis(): void
    {
        if (!class_exists(RedisReceivedStamp::class)) {
            $this->markTestSkipped();

            return;
        }

        $message = new \stdClass();

        $provider = new MessageAttributeProvider();

        $envelope = new Envelope($message, [new RedisReceivedStamp('some-id')]);
        $attributes = $provider->getAttributes($envelope);
        $this->assertEquals('redis', $attributes['messaging.system']);
    }

    public function testItSetsMessagingSystemIfAmqp(): void
    {
        if (!class_exists(\AMQPEnvelope::class) || !class_exists(AmqpReceivedStamp::class)) {
            $this->markTestSkipped();

            return;
        }

        $amqpEnvelope = $this->createMock(\AMQPEnvelope::class);

        $provider = new MessageAttributeProvider();

        $message = new \stdClass();
        $envelope = new Envelope($message, [new AmqpReceivedStamp($amqpEnvelope, 'jobs')]);
        $attributes = $provider->getAttributes($envelope);
        $this->assertEquals('rabbitmq', $attributes['messaging.system']);
    }
}
