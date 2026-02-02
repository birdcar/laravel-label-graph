<?php

declare(strict_types=1);

namespace Birdcar\LabelTree\Query;

use Illuminate\Database\Eloquent\Builder;

interface PathQueryAdapter
{
    /**
     * Apply pattern matching to query.
     *
     * @param  string  $column  Column name (usually 'path')
     * @param  string  $pattern  Pattern with * (single) and ** (multi) wildcards
     */
    public function wherePathMatches(Builder $query, string $column, string $pattern): Builder;

    /**
     * Apply LIKE-style pattern matching.
     */
    public function wherePathLike(Builder $query, string $column, string $pattern): Builder;

    /**
     * Find ancestors of a path.
     */
    public function whereAncestorOf(Builder $query, string $column, string $path): Builder;

    /**
     * Find descendants of a path.
     */
    public function whereDescendantOf(Builder $query, string $column, string $path): Builder;

    /**
     * Check if ltree extension is available (Postgres only).
     */
    public function hasLtreeSupport(): bool;

    /**
     * Apply ltxtquery text pattern matching.
     *
     * Matches paths containing labels matching the boolean expression,
     * regardless of their position in the path.
     *
     * @param  string  $column  Column name (usually 'path')
     * @param  string  $pattern  Boolean expression (e.g., 'Europe & Asia')
     */
    public function wherePathMatchesText(Builder $query, string $column, string $pattern): Builder;
}
