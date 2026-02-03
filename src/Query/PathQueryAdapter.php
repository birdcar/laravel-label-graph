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

    /**
     * Check if array operators are supported.
     */
    public function supportsArrayOperators(): bool;

    /**
     * Check if any path in array contains an ancestor of the given path.
     * PostgreSQL: ltree[] @> ltree
     *
     * @param  array<int, string>  $paths
     */
    public function wherePathHasAncestorIn(Builder $query, string $column, array $paths): Builder;

    /**
     * Check if any path in array contains a descendant of the given path.
     * PostgreSQL: ltree[] <@ ltree
     *
     * @param  array<int, string>  $paths
     */
    public function wherePathHasDescendantIn(Builder $query, string $column, array $paths): Builder;

    /**
     * Check if any path in array matches the lquery pattern.
     * PostgreSQL: ltree[] ~ lquery
     *
     * @param  array<int, string>  $paths
     */
    public function whereAnyPathMatches(Builder $query, string $column, array $paths, string $pattern): Builder;

    /**
     * Get first ancestor of path from array, or null.
     * PostgreSQL: ltree[] ?@> ltree
     *
     * @param  array<int, string>  $candidates
     */
    public function firstAncestorFrom(string $path, array $candidates): ?string;

    /**
     * Get first descendant of path from array, or null.
     * PostgreSQL: ltree[] ?<@ ltree
     *
     * @param  array<int, string>  $candidates
     */
    public function firstDescendantFrom(string $path, array $candidates): ?string;
}
