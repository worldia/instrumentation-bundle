<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\Attribute;

use Symfony\Component\Messenger\Envelope;

interface MessageAttributeProviderInterface
{
    /**
     * @return array{
     *           'messenger.message':class-string,
     *           'messenger.bus'?:string,
     *           'messaging.system'?:'rabbitmq'|'redis',
     *           'messaging.message_id'?:string
     *         }
     */
    public function getAttributes(Envelope $envelope): array;
}
