<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\Attribute;

use Doctrine\DBAL\Platforms;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use OpenTelemetry\SemConv\TraceAttributes;

class DoctrineConnectionAttributeProvider implements DoctrineConnectionAttributeProviderInterface
{
    public function getAttributes(AbstractPlatform $platform, array $params): array
    {
        $attributes = [TraceAttributes::DB_SYSTEM => $this->getSystemAttribute($platform)];

        if (isset($params['user'])) {
            $attributes['db.user'] = $params['user'];
        }

        if (isset($params['dbname'])) {
            $attributes[TraceAttributes::DB_NAMESPACE] = $params['dbname'];
        }

        if (isset($params['host']) && !empty($params['host']) && !isset($params['memory'])) {
            if (false === filter_var($params['host'], \FILTER_VALIDATE_IP)) {
                $attributes[TraceAttributes::SERVER_ADDRESS] = $params['host'];
            } else {
                $attributes[TraceAttributes::NETWORK_PEER_ADDRESS] = $params['host'];
            }
        }

        if (isset($params['port'])) {
            $attributes[TraceAttributes::SERVER_PORT] = (string) $params['port'];
        }

        if (isset($params['unix_socket'])) {
            $attributes[TraceAttributes::NETWORK_TRANSPORT] = 'unix';
        } elseif (isset($params['memory'])) {
            $attributes[TraceAttributes::NETWORK_TRANSPORT] = 'inproc';
        }

        return $attributes;
    }

    private function getSystemAttribute(AbstractPlatform $platform): string
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
