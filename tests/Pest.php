<?php

declare(strict_types=1);

use Birdcar\LabelTree\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit', 'Benchmark');

/**
 * Get the current database driver being used for tests.
 */
function currentDriver(): string
{
    return env('DB_CONNECTION', 'testing') === 'testing'
        ? 'sqlite'
        : env('DB_CONNECTION', 'sqlite');
}

/**
 * Check if running against SQLite.
 */
function usingSqlite(): bool
{
    return currentDriver() === 'sqlite' || currentDriver() === 'testing';
}

/**
 * Check if running against MySQL.
 */
function usingMysql(): bool
{
    return currentDriver() === 'mysql';
}

/**
 * Check if running against PostgreSQL.
 */
function usingPostgres(): bool
{
    return currentDriver() === 'pgsql';
}
