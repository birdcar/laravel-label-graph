<?php

declare(strict_types=1);

use Birdcar\LabelGraph\Query\AdapterFactory;
use Birdcar\LabelGraph\Query\MySqlAdapter;
use Birdcar\LabelGraph\Query\PostgresAdapter;
use Birdcar\LabelGraph\Query\SqliteAdapter;

beforeEach(function (): void {
    $this->factory = new AdapterFactory;
});

it('creates SqliteAdapter for sqlite driver', function (): void {
    // Just test the factory logic, not actual DB connection
    config(['database.connections.test_sqlite' => ['driver' => 'sqlite']]);

    expect($this->factory->make('test_sqlite'))->toBeInstanceOf(SqliteAdapter::class);
});

it('creates MySqlAdapter for mysql driver', function (): void {
    config(['database.connections.test_mysql' => ['driver' => 'mysql']]);

    expect($this->factory->make('test_mysql'))->toBeInstanceOf(MySqlAdapter::class);
});

it('creates MySqlAdapter for mariadb driver', function (): void {
    config(['database.connections.test_mariadb' => ['driver' => 'mariadb']]);

    expect($this->factory->make('test_mariadb'))->toBeInstanceOf(MySqlAdapter::class);
});

it('creates PostgresAdapter for pgsql driver', function (): void {
    config(['database.connections.test_pgsql' => ['driver' => 'pgsql']]);

    expect($this->factory->make('test_pgsql'))->toBeInstanceOf(PostgresAdapter::class);
});

it('throws exception for unsupported driver', function (): void {
    config(['database.connections.test_unsupported' => ['driver' => 'unsupported']]);

    $this->factory->make('test_unsupported');
})->throws(InvalidArgumentException::class, 'Unsupported database driver: unsupported');

it('uses default connection when none specified', function (): void {
    config(['database.default' => 'testing']);
    // Testing connection already has sqlite driver

    expect($this->factory->make())->toBeInstanceOf(SqliteAdapter::class);
})->skip(fn () => ! usingSqlite(), 'Requires SQLite testing connection');
