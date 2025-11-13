<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Semantics\Attribute;

use Doctrine\DBAL\Platforms;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use OpenTelemetry\SemConv\Attributes\DbAttributes;
use OpenTelemetry\SemConv\Attributes\NetworkAttributes;
use OpenTelemetry\SemConv\Attributes\ServerAttributes;
use OpenTelemetry\SemConv\Incubating\Attributes\DbIncubatingAttributes;

class DoctrineConnectionAttributeProvider implements DoctrineConnectionAttributeProviderInterface
{
    public function getAttributes(AbstractPlatform $platform, array $params): array
    {
        $attributes = [DbAttributes::DB_SYSTEM_NAME => $this->getSystemAttribute($platform)];

        if (isset($params['user'])) {
            $attributes['db.user'] = $params['user'];
        }

        if (isset($params['dbname'])) {
            $attributes[DbAttributes::DB_NAMESPACE] = $params['dbname'];
        }

        if (isset($params['host']) && !empty($params['host']) && !isset($params['memory'])) {
            if (false === filter_var($params['host'], \FILTER_VALIDATE_IP)) {
                $attributes[ServerAttributes::SERVER_ADDRESS] = $params['host'];
            } else {
                $attributes[NetworkAttributes::NETWORK_PEER_ADDRESS] = $params['host'];
            }
        }

        if (isset($params['port'])) {
            $attributes[ServerAttributes::SERVER_PORT] = (string) $params['port'];
        }

        if (isset($params['unix_socket'])) {
            $attributes[NetworkAttributes::NETWORK_TRANSPORT] = 'unix';
        } elseif (isset($params['memory'])) {
            $attributes[NetworkAttributes::NETWORK_TRANSPORT] = 'inproc';
        }

        return $attributes;
    }

    private function getSystemAttribute(AbstractPlatform $platform): string
    {
        if ($platform instanceof Platforms\MariaDBPlatform) {
            return DbAttributes::DB_SYSTEM_NAME_VALUE_MARIADB;
        }
        if ($platform instanceof Platforms\PostgreSQLPlatform) {
            return DbAttributes::DB_SYSTEM_NAME_VALUE_POSTGRESQL;
        }
        if ($platform instanceof Platforms\AbstractMySQLPlatform) {
            return DbAttributes::DB_SYSTEM_NAME_VALUE_MYSQL;
        }
        if ($platform instanceof Platforms\SQLServerPlatform) {
            return DbAttributes::DB_SYSTEM_NAME_VALUE_MICROSOFT_SQL_SERVER;
        }
        if ($platform instanceof Platforms\SqlitePlatform) {
            return DbIncubatingAttributes::DB_SYSTEM_NAME_VALUE_SQLITE;
        }
        if ($platform instanceof Platforms\OraclePlatform) {
            return DbIncubatingAttributes::DB_SYSTEM_NAME_VALUE_ORACLE_DB;
        }
        if ($platform instanceof Platforms\DB2Platform) {
            return DbIncubatingAttributes::DB_SYSTEM_NAME_VALUE_IBM_DB2;
        }

        return DbIncubatingAttributes::DB_SYSTEM_NAME_VALUE_OTHER_SQL;
    }
}
