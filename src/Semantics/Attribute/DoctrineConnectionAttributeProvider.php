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
        if ($platform instanceof Platforms\SQLitePlatform) {
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
