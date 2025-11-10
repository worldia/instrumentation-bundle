<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Semantics\Attribute;

use Doctrine\DBAL\Platforms;
use Instrumentation\Semantics\Attribute\DoctrineConnectionAttributeProvider;
use Instrumentation\Semantics\Attribute\DoctrineConnectionAttributeProviderInterface;
use PHPUnit\Framework\TestCase;

class DoctrineConnectionAttributeProviderTest extends TestCase
{
    public function testItImplementsServerRequestAttributeProviderInterface(): void
    {
        $this->assertTrue(is_a(DoctrineConnectionAttributeProvider::class, DoctrineConnectionAttributeProviderInterface::class, true));
    }

    public function testItSetsDbSystem(): void
    {
        $provider = new DoctrineConnectionAttributeProvider();

        foreach ([
            Platforms\MariaDBPlatform::class => 'mariadb',
            Platforms\PostgreSQLPlatform::class => 'postgresql',
            Platforms\AbstractMySQLPlatform::class => 'mysql',
            Platforms\SQLServerPlatform::class => 'mssql',
            Platforms\SqlitePlatform::class => 'sqlite',
            Platforms\OraclePlatform::class => 'oracle',
            Platforms\DB2Platform::class => 'db2',
        ] as $class => $name) {
            $attributes = $provider->getAttributes($this->createMock($class), []);
            $this->assertEquals($name, $attributes['db.system.name']);
        }
    }

    public function testItSetsDbName(): void
    {
        $params = [
            'dbname' => 'app',
            'user' => 'client',
            'port' => 3306,
        ];

        $provider = new DoctrineConnectionAttributeProvider();
        $attributes = $provider->getAttributes($this->createMock(Platforms\MariaDBPlatform::class), $params);

        $this->assertEquals('app', $attributes['db.namespace']);
        $this->assertEquals('client', $attributes['db.user']);
        $this->assertEquals('3306', $attributes['server.port']);
    }
}
