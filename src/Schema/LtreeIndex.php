<?php

declare(strict_types=1);

namespace Birdcar\LabelTree\Schema;

use Birdcar\LabelTree\Exceptions\UnsupportedDatabaseException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

final class LtreeIndex
{
    /**
     * Create optimized ltree index based on database.
     */
    public static function create(Blueprint $table, string $column, ?string $name = null): void
    {
        $driver = DB::connection()->getDriverName();
        $indexName = $name ?? "idx_{$table->getTable()}_{$column}_ltree";

        match ($driver) {
            'pgsql' => self::createPostgresIndex($table, $column, $indexName),
            'mysql' => self::createMysqlIndex($table, $column, $indexName),
            'sqlite' => self::createSqliteIndex($table, $column, $indexName),
            default => $table->index($column, $indexName),
        };
    }

    /**
     * Create PostgreSQL GiST index for ltree operations.
     */
    public static function createGist(
        Blueprint $table,
        string $column,
        ?string $name = null,
        int $siglen = 8
    ): void {
        $driver = DB::connection()->getDriverName();

        if ($driver !== 'pgsql') {
            throw UnsupportedDatabaseException::gistIndex($driver);
        }

        $indexName = $name ?? "idx_{$table->getTable()}_{$column}_gist";

        // Check if ltree extension exists
        $hasLtree = DB::selectOne("SELECT 1 FROM pg_extension WHERE extname = 'ltree'");

        if ($hasLtree) {
            DB::statement(
                "CREATE INDEX {$indexName} ON {$table->getTable()} ".
                "USING GIST ({$column}::ltree gist_ltree_ops(siglen={$siglen}))"
            );
        } else {
            // Fall back to B-tree if ltree not available
            $table->index($column, $indexName);
        }
    }

    /**
     * Drop ltree index.
     */
    public static function drop(Blueprint $table, string $column, ?string $name = null): void
    {
        $indexName = $name ?? "idx_{$table->getTable()}_{$column}_ltree";
        $table->dropIndex($indexName);
    }

    private static function createPostgresIndex(Blueprint $table, string $column, string $name): void
    {
        // Check if ltree extension exists
        $hasLtree = DB::selectOne("SELECT 1 FROM pg_extension WHERE extname = 'ltree'");

        if ($hasLtree) {
            // GiST index for ltree operations
            DB::statement(
                "CREATE INDEX {$name} ON {$table->getTable()} ".
                "USING GIST ({$column}::ltree)"
            );
        } else {
            // B-tree for text column
            $table->index($column, $name);
        }
    }

    private static function createMysqlIndex(Blueprint $table, string $column, string $name): void
    {
        // Composite index on (path, depth) for common queries
        // Note: path is limited to 768 chars, within MySQL key limit
        $depthColumn = 'depth';

        // Check if depth column exists on table
        $columns = array_map(fn ($col) => $col->name ?? $col['name'], $table->getColumns());

        if (in_array($depthColumn, $columns, true)) {
            // Use raw statement for composite with prefix
            DB::statement(
                "CREATE INDEX {$name} ON {$table->getTable()} ({$column}(255), {$depthColumn})"
            );
        } else {
            // Path-only index with prefix for long paths
            DB::statement(
                "CREATE INDEX {$name} ON {$table->getTable()} ({$column}(255))"
            );
        }
    }

    private static function createSqliteIndex(Blueprint $table, string $column, string $name): void
    {
        // Standard B-tree index (SQLite doesn't support GiST)
        $table->index($column, $name);
    }
}
