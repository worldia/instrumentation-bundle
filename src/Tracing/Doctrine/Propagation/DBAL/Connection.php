<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Instrumentation\Tracing\Doctrine\Propagation\DBAL;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Statement;
use Instrumentation\Tracing\Doctrine\Propagation\TraceContextInfoProviderInterface;

final class Connection extends AbstractConnectionMiddleware
{
    public function __construct(ConnectionInterface $decorated, private TraceContextInfoProviderInterface $infoProvider)
    {
        parent::__construct($decorated);
    }

    public function prepare(string $sql): Statement
    {
        $sql .= self::formatComments($this->infoProvider->getTraceContext());

        return parent::prepare($sql);
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
