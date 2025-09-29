<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Doctrine\Propagation\DBAL;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Instrumentation\Tracing\Doctrine\Propagation\TraceContextInfoProviderInterface;

final class Connection implements ConnectionInterface
{
    public function __construct(private ConnectionInterface $decorated, private TraceContextInfoProviderInterface $infoProvider)
    {
    }

    public function prepare(string $sql): Statement
    {
        $sql .= self::formatComments($this->infoProvider->getTraceContext());

        return $this->decorated->prepare($sql);
    }

    public function query(string $sql): Result
    {
        return $this->decorated->query($sql);
    }

    public function quote(string $value): string
    {
        return $this->decorated->quote($value);
    }

    public function exec(string $sql): int|string
    {
        return $this->decorated->exec($sql);
    }

    public function lastInsertId(): string|int
    {
        return $this->decorated->lastInsertId();
    }

    public function beginTransaction(): void
    {
        $this->decorated->beginTransaction();
    }

    public function commit(): void
    {
        $this->decorated->commit();
    }

    public function rollBack(): void
    {
        $this->decorated->rollBack();
    }

    public function getServerVersion(): string
    {
        return $this->decorated->getServerVersion();
    }

    /**
     * @return object|resource
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
                static fn (string $value, string $key) => $key.'='.str_replace('%', '%%', $value), $comments,
                array_keys($comments)
            ),
        ).'*/';
    }
}
