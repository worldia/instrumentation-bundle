<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\Attribute;

use Doctrine\DBAL\Platforms;
use OpenTelemetry\SemConv\TraceAttributes;

class DoctrineConnectionAttributeProvider implements DoctrineConnectionAttributeProviderInterface
{
    public function getAttributes(Platforms\AbstractPlatform $platform, array $params): array
    {
        $attributes = [TraceAttributes::DB_SYSTEM => $this->getSystemAttribute($platform)];

        if (isset($params['user'])) {
            $attributes[TraceAttributes::DB_USER] = $params['user'];
        }

        if (isset($params['dbname'])) {
            $attributes[TraceAttributes::DB_NAME] = $params['dbname'];
        }

        if (isset($params['host']) && !empty($params['host']) && !isset($params['memory'])) {
            if (false === filter_var($params['host'], \FILTER_VALIDATE_IP)) {
                $attributes[TraceAttributes::NET_PEER_NAME] = $params['host'];
            } else {
                $attributes[TraceAttributes::NET_PEER_IP] = $params['host'];
            }
        }

        if (isset($params['port'])) {
            $attributes[TraceAttributes::NET_PEER_PORT] = (string) $params['port'];
        }

        if (isset($params['unix_socket'])) {
            $attributes[TraceAttributes::NET_TRANSPORT] = 'unix';
        } elseif (isset($params['memory'])) {
            $attributes[TraceAttributes::NET_TRANSPORT] = 'inproc';
        }

        return $attributes;
    }

    private function getSystemAttribute(Platforms\AbstractPlatform $platform): string
    {
        if ($platform instanceof Platforms\MariaDBPlatform) {
            return 'mariadb';
        }
        if ($platform instanceof Platforms\PostgreSQLPlatform) {
            return 'postgresql';
        }
        if ($platform instanceof Platforms\AbstractMySQLPlatform) {
            return 'mysql';
        }
        if ($platform instanceof Platforms\SQLServerPlatform) {
            return 'mssql';
        }
        if ($platform instanceof Platforms\SqlitePlatform) {
            return 'sqlite';
        }
        if ($platform instanceof Platforms\OraclePlatform) {
            return 'oracle';
        }
        if ($platform instanceof Platforms\DB2Platform) {
            return 'db2';
        }

        return 'other_sql';
    }
}
