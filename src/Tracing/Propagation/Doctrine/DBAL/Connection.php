<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Propagation\Doctrine\DBAL;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\ServerInfoAwareConnection;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;
use Instrumentation\Tracing\Propagation\Doctrine\TraceContextInfoProviderInterface;

final class Connection implements ServerInfoAwareConnection
{
    public function __construct(private ConnectionInterface $decorated, private TraceContextInfoProviderInterface $infoProvider)
    {
    }

    public function prepare(string $sql): Statement
    {
        $sql = $sql.self::formatComments($this->infoProvider->getTraceContext());

        return $this->decorated->prepare($sql);
    }

    public function query(string $sql): Result
    {
        return $this->decorated->query($sql);
    }

    public function quote($value, $type = ParameterType::STRING)
    {
        return $this->decorated->quote($value, $type);
    }

    public function exec(string $sql): int
    {
        return $this->decorated->exec($sql);
    }

    public function lastInsertId($name = null)
    {
        return $this->decorated->lastInsertId($name);
    }

    public function beginTransaction(): bool
    {
        return $this->decorated->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->decorated->commit();
    }

    public function rollBack(): bool
    {
        return $this->decorated->rollBack();
    }

    public function getServerVersion(): string
    {
        if ($this->decorated instanceof ServerInfoAwareConnection) {
            return $this->decorated->getServerVersion();
        }

        return 'unknown';
    }

    /**
     * @return \PDO|object|resource
     */
    public function getNativeConnection()
    {
        return $this->decorated->getNativeConnection();
    }

    /**
     * @param array<string,string> $comments
     */
    private static function formatComments(array $comments): string
    {
        if (empty($comments)) {
            return '';
        }

        return '/*'.implode(
            ',',
            array_map(
                static fn (string $value, string $key) => self::escapeUrlEncode($key)."='".self::escapeUrlEncode($value)."'", $comments,
                array_keys($comments)
            ),
        ).'*/';
    }

    /**
     * Since SQL uses '%' as a keyword, '%' is a by-product of url quoting
     *   e.g. foo,bar --> foo%2Cbar
     * thus in our quoting, we need to escape it too to finally give
     *        foo,bar --> foo%%2Cbar.
     */
    private static function escapeUrlEncode(string $input): string
    {
        return str_replace('%', '%%', urlencode($input));
    }
}
