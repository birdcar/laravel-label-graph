<?php

declare(strict_types=1);

namespace Birdcar\LabelTree\Query;

use Birdcar\LabelTree\Exceptions\UnsupportedDatabaseException;
use Birdcar\LabelTree\Query\Lquery\Lquery;
use Birdcar\LabelTree\Query\Ltxtquery\Ltxtquery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MySqlAdapter implements PathQueryAdapter
{
    public function wherePathMatches(Builder $query, string $column, string $pattern): Builder
    {
        // Check if pattern needs hybrid matching (regex + PHP post-filter)
        if (Lquery::needsHybridMatch($pattern)) {
            // Use loose regex that over-matches, caller must post-filter
            $looseRegex = Lquery::toLooseRegex($pattern);

            return $query->whereRaw("{$column} REGEXP ?", [$looseRegex]);
        }

        $regex = Lquery::toRegex($pattern);

        return $query->whereRaw("{$column} REGEXP ?", [$regex]);
    }

    public function wherePathLike(Builder $query, string $column, string $pattern): Builder
    {
        return $query->where($column, 'LIKE', $pattern);
    }

    public function whereAncestorOf(Builder $query, string $column, string $path): Builder
    {
        $prefixes = $this->buildPrefixes($path);

        if ($prefixes === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $prefixes);
    }

    public function whereDescendantOf(Builder $query, string $column, string $path): Builder
    {
        return $query->where($column, 'LIKE', "{$path}.%");
    }

    public function hasLtreeSupport(): bool
    {
        return false;
    }

    /**
     * Build all prefixes of a path (excluding the path itself).
     *
     * @return array<int, string>
     */
    protected function buildPrefixes(string $path): array
    {
        $segments = explode('.', $path);
        $prefixes = [];
        $current = '';

        foreach ($segments as $i => $segment) {
            $current = $i === 0 ? $segment : "{$current}.{$segment}";
            if ($current !== $path) {
                $prefixes[] = $current;
            }
        }

        return $prefixes;
    }

    public function wherePathMatchesText(Builder $query, string $column, string $pattern): Builder
    {
        $predicate = Ltxtquery::toPredicate($pattern);
        $table = $query->getModel()->getTable();

        // Get matching paths by filtering in PHP
        $matchingPaths = DB::table($table)
            ->pluck($column)
            ->filter($predicate)
            ->values()
            ->all();

        if ($matchingPaths === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $matchingPaths);
    }

    public function supportsArrayOperators(): bool
    {
        return false;
    }

    public function wherePathHasAncestorIn(Builder $query, string $column, array $paths): Builder
    {
        throw UnsupportedDatabaseException::arrayOperators('mysql');
    }

    public function wherePathHasDescendantIn(Builder $query, string $column, array $paths): Builder
    {
        throw UnsupportedDatabaseException::arrayOperators('mysql');
    }

    public function whereAnyPathMatches(Builder $query, string $column, array $paths, string $pattern): Builder
    {
        throw UnsupportedDatabaseException::arrayOperators('mysql');
    }

    public function firstAncestorFrom(string $path, array $candidates): ?string
    {
        throw UnsupportedDatabaseException::arrayOperators('mysql');
    }

    public function firstDescendantFrom(string $path, array $candidates): ?string
    {
        throw UnsupportedDatabaseException::arrayOperators('mysql');
    }
}
