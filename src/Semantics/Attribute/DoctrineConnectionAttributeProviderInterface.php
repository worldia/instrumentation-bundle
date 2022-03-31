<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\Attribute;

use Doctrine\DBAL\Platforms\AbstractPlatform;

interface DoctrineConnectionAttributeProviderInterface
{
    /**
     * @param array<string,mixed> $connectionParams
     *
     * @return array{
     *           'db.system':string,
     *           'db.user'?:string,
     *           'db.name'?:string,
     *           'net.peer.name'?:string,
     *           'net.peer.ip'?:string,
     *           'net.peer.port'?:string,
     *           'net.transport'?:string,
     *         }
     */
    public function getAttributes(AbstractPlatform $platform, array $connectionParams): array;
}
