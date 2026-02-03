<?php

declare(strict_types=1);

namespace Birdcar\LabelGraph\Tests;

use Birdcar\LabelGraph\LabelGraphServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LabelGraphServiceProvider::class,
        ];
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $driver = env('DB_CONNECTION', 'testing');

        match ($driver) {
            'mysql' => $this->configureMysql($app),
            'pgsql' => $this->configurePostgres($app),
            default => $this->configureSqlite($app),
        };
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function configureSqlite($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function configureMysql($app): void
    {
        $app['config']->set('database.default', 'mysql');
        $app['config']->set('database.connections.mysql', [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '13306'),
            'database' => env('DB_DATABASE', 'laravel_label_graph'),
            'username' => env('DB_USERNAME', 'labelgraph'),
            'password' => env('DB_PASSWORD', 'labelgraph'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ]);
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function configurePostgres($app): void
    {
        $app['config']->set('database.default', 'pgsql');
        $app['config']->set('database.connections.pgsql', [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '15432'),
            'database' => env('DB_DATABASE', 'laravel_label_graph'),
            'username' => env('DB_USERNAME', 'labelgraph'),
            'password' => env('DB_PASSWORD', 'labelgraph'),
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
        ]);
    }
}
