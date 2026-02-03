<?php

declare(strict_types=1);

namespace Birdcar\LabelGraph\Ltree;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Builds database-specific SQL expressions for ltree functions.
 */
final class LtreeExpression
{
    public function __construct(
        private readonly string $driver,
        private readonly bool $hasLtree = false,
    ) {}

    /**
     * Build nlevel() expression.
     */
    public function nlevel(string $column): Expression
    {
        if ($this->hasLtree) {
            return DB::raw("nlevel({$column}::ltree)");
        }

        return match ($this->driver) {
            'pgsql' => DB::raw("(CASE WHEN {$column} = '' THEN 0 ELSE LENGTH({$column}) - LENGTH(REPLACE({$column}, '.', '')) + 1 END)"),
            'mysql' => DB::raw("(CASE WHEN {$column} = '' THEN 0 ELSE LENGTH({$column}) - LENGTH(REPLACE({$column}, '.', '')) + 1 END)"),
            'sqlite' => DB::raw("ltree_nlevel({$column})"),
            default => throw new RuntimeException("Unsupported driver: {$this->driver}"),
        };
    }

    /**
     * Build subpath() expression.
     *
     * Note: Complex cases (negative offset, length) require PHP post-processing.
     */
    public function subpath(string $column, int $offset, ?int $len = null): Expression
    {
        if ($this->hasLtree) {
            $args = $len !== null
                ? "{$column}::ltree, {$offset}, {$len}"
                : "{$column}::ltree, {$offset}";

            return DB::raw("subpath({$args})::text");
        }

        // For non-ltree databases, complex cases require PHP post-processing
        if ($offset < 0 || ($len !== null && $len < 0)) {
            throw new RuntimeException('Complex subpath() with negative offset/length requires PHP post-processing');
        }

        return match ($this->driver) {
            'pgsql' => $this->pgsqlSubpath($column, $offset, $len),
            'mysql' => $this->mysqlSubpath($column, $offset, $len),
            'sqlite' => $this->sqliteSubpath($column, $offset, $len),
            default => throw new RuntimeException("Unsupported driver: {$this->driver}"),
        };
    }

    private function pgsqlSubpath(string $column, int $offset, ?int $len): Expression
    {
        if ($offset === 0 && $len === null) {
            return DB::raw($column);
        }

        // Use array operations for PostgreSQL without ltree
        // split_part doesn't work well for subpaths, use regexp_split_to_array
        if ($len === null) {
            // Get from offset to end
            return DB::raw(
                "array_to_string((string_to_array({$column}, '.'))[$offset + 1:], '.')"
            );
        }

        // Get specific length
        return DB::raw(
            "array_to_string((string_to_array({$column}, '.'))[$offset + 1:$offset + {$len}], '.')"
        );
    }

    private function mysqlSubpath(string $column, int $offset, ?int $len): Expression
    {
        if ($offset === 0 && $len === null) {
            return DB::raw($column);
        }

        // MySQL SUBSTRING_INDEX is limited - can only get from start or end
        if ($offset === 0) {
            // Get first N segments (len is guaranteed non-null here due to above check)
            return DB::raw("SUBSTRING_INDEX({$column}, '.', {$len})");
        }

        if ($len === null) {
            // Get from offset to end: skip first N segments
            // SUBSTRING_INDEX(col, '.', -count) gets last count segments
            // We need: total_segments - offset segments from end
            return DB::raw(
                "SUBSTRING_INDEX({$column}, '.', -((LENGTH({$column}) - LENGTH(REPLACE({$column}, '.', ''))) + 1 - {$offset}))"
            );
        }

        // Complex case: offset > 0 and specific length
        // This requires nested SUBSTRING_INDEX calls
        throw new RuntimeException('MySQL subpath() with offset > 0 and length requires PHP post-processing');
    }

    private function sqliteSubpath(string $column, int $offset, ?int $len): Expression
    {
        if ($len === null) {
            return DB::raw("ltree_subpath({$column}, {$offset})");
        }

        return DB::raw("ltree_subpath_len({$column}, {$offset}, {$len})");
    }

    /**
     * Build subltree() expression (start to end position).
     */
    public function subltree(string $column, int $start, int $end): Expression
    {
        if ($start >= $end) {
            return DB::raw("''");
        }

        return $this->subpath($column, $start, $end - $start);
    }

    /**
     * Build index() expression (find subpath position).
     *
     * Note: Most databases can't express this natively without ltree.
     */
    public function index(string $column, string $subpath): Expression
    {
        $escapedSubpath = addslashes($subpath);

        if ($this->hasLtree) {
            return DB::raw("index({$column}::ltree, '{$escapedSubpath}'::ltree)");
        }

        return match ($this->driver) {
            'sqlite' => DB::raw("ltree_index({$column}, '{$escapedSubpath}')"),
            default => throw new RuntimeException('index() requires PHP post-processing on this database'),
        };
    }

    /**
     * Build concat expression.
     */
    public function concat(string $column1, string $column2): Expression
    {
        if ($this->hasLtree) {
            return DB::raw("({$column1}::ltree || {$column2}::ltree)::text");
        }

        return match ($this->driver) {
            'pgsql' => DB::raw("CASE WHEN {$column1} = '' THEN {$column2} WHEN {$column2} = '' THEN {$column1} ELSE {$column1} || '.' || {$column2} END"),
            'mysql' => DB::raw("CASE WHEN {$column1} = '' THEN {$column2} WHEN {$column2} = '' THEN {$column1} ELSE CONCAT({$column1}, '.', {$column2}) END"),
            'sqlite' => DB::raw("CASE WHEN {$column1} = '' THEN {$column2} WHEN {$column2} = '' THEN {$column1} ELSE {$column1} || '.' || {$column2} END"),
            default => throw new RuntimeException("Unsupported driver: {$this->driver}"),
        };
    }
}
