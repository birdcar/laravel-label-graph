<?php

declare(strict_types=1);

namespace Birdcar\LabelGraph\Query;

use InvalidArgumentException;

class AdapterFactory
{
    public function make(?string $connection = null): PathQueryAdapter
    {
        $connection = $connection ?? config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        return match ($driver) {
            'pgsql' => new PostgresAdapter,
            'mysql', 'mariadb' => new MySqlAdapter,
            'sqlite' => new SqliteAdapter,
            default => throw new InvalidArgumentException(
                "Unsupported database driver: {$driver}"
            ),
        };
    }
}
