<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsMiddleware;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

#[AsMiddleware]
final class SqlitePragmasMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return new class($driver) extends AbstractDriverMiddleware {
            private const array PRAGMAS = [
                'PRAGMA journal_mode = WAL',
                'PRAGMA busy_timeout = 5000',
                'PRAGMA synchronous = NORMAL',
                'PRAGMA foreign_keys = ON',
            ];

            public function connect(#[\SensitiveParameter] array $params): Connection
            {
                $connection = parent::connect($params);

                if (!str_contains($params['driver'] ?? '', 'sqlite')) {
                    return $connection;
                }

                foreach (self::PRAGMAS as $pragma) {
                    $connection->exec($pragma);
                }

                return $connection;
            }
        };
    }
}
