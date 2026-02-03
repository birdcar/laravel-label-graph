<?php

declare(strict_types=1);

namespace Birdcar\LabelTree\Query;

use Birdcar\LabelTree\Exceptions\UnsupportedDatabaseException;
use Birdcar\LabelTree\Query\Lquery\Lquery;
use Birdcar\LabelTree\Query\Ltxtquery\Ltxtquery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PostgresAdapter implements PathQueryAdapter
{
    protected ?bool $ltreeAvailable = null;

    public function wherePathMatches(Builder $query, string $column, string $pattern): Builder
    {
        // Check if pattern needs hybrid matching (regex + PHP post-filter)
        if (Lquery::needsHybridMatch($pattern)) {
            // Use loose regex that over-matches, caller must post-filter
            $looseRegex = Lquery::toLooseRegex($pattern);

            if ($this->hasLtreeSupport()) {
                // Even with ltree, use regex for loose matching
                return $query->whereRaw("{$column} ~ ?", [$looseRegex]);
            }

            return $query->whereRaw("{$column} ~ ?", [$looseRegex]);
        }

        if ($this->hasLtreeSupport()) {
            // Use native lquery - parse and recompile to ensure valid syntax
            $lquery = Lquery::toLquery($pattern);

            return $query->whereRaw("{$column}::ltree ~ ?::lquery", [$lquery]);
        }

        // Fall back to regex
        $regex = Lquery::toRegex($pattern);

        return $query->whereRaw("{$column} ~ ?", [$regex]);
    }

    public function wherePathLike(Builder $query, string $column, string $pattern): Builder
    {
        return $query->where($column, 'LIKE', $pattern);
    }

    public function whereAncestorOf(Builder $query, string $column, string $path): Builder
    {
        if ($this->hasLtreeSupport()) {
            return $query->whereRaw("?::ltree <@ {$column}::ltree", [$path]);
        }

        $prefixes = $this->buildPrefixes($path);

        if ($prefixes === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $prefixes);
    }

    public function whereDescendantOf(Builder $query, string $column, string $path): Builder
    {
        if ($this->hasLtreeSupport()) {
            return $query->whereRaw("{$column}::ltree <@ ?::ltree", [$path]);
        }

        return $query->where($column, 'LIKE', "{$path}.%");
    }

    public function hasLtreeSupport(): bool
    {
        if ($this->ltreeAvailable === null) {
            try {
                $result = DB::select("SELECT 1 FROM pg_extension WHERE extname = 'ltree'");
                $this->ltreeAvailable = count($result) > 0;
            } catch (\Exception) {
                $this->ltreeAvailable = false;
            }
        }

        return $this->ltreeAvailable;
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
        if ($this->hasLtreeSupport()) {
            $ltxtquery = Ltxtquery::toNative($pattern);

            return $query->whereRaw("{$column}::ltree @ ?::ltxtquery", [$ltxtquery]);
        }

        // Fall back to fetching all paths and filtering in PHP
        return $this->predicateFilter($query, $column, $pattern);
    }

    protected function predicateFilter(Builder $query, string $column, string $pattern): Builder
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
        return $this->hasLtreeSupport();
    }

    public function wherePathHasAncestorIn(Builder $query, string $column, array $paths): Builder
    {
        if (! $this->supportsArrayOperators()) {
            throw UnsupportedDatabaseException::arrayOperators('pgsql (no ltree)');
        }

        if ($paths === []) {
            return $query->whereRaw('1 = 0');
        }

        $arrayLiteral = $this->buildLtreeArray($paths);

        return $query->whereRaw("ARRAY[{$arrayLiteral}]::ltree[] @> {$column}::ltree");
    }

    public function wherePathHasDescendantIn(Builder $query, string $column, array $paths): Builder
    {
        if (! $this->supportsArrayOperators()) {
            throw UnsupportedDatabaseException::arrayOperators('pgsql (no ltree)');
        }

        if ($paths === []) {
            return $query->whereRaw('1 = 0');
        }

        $arrayLiteral = $this->buildLtreeArray($paths);

        return $query->whereRaw("ARRAY[{$arrayLiteral}]::ltree[] <@ {$column}::ltree");
    }

    public function whereAnyPathMatches(Builder $query, string $column, array $paths, string $pattern): Builder
    {
        if (! $this->supportsArrayOperators()) {
            throw UnsupportedDatabaseException::arrayOperators('pgsql (no ltree)');
        }

        if ($paths === []) {
            return $query->whereRaw('1 = 0');
        }

        $arrayLiteral = $this->buildLtreeArray($paths);
        $lquery = Lquery::toLquery($pattern);

        return $query->whereRaw("ARRAY[{$arrayLiteral}]::ltree[] ~ ?::lquery", [$lquery]);
    }

    public function firstAncestorFrom(string $path, array $candidates): ?string
    {
        if (! $this->supportsArrayOperators()) {
            throw UnsupportedDatabaseException::arrayOperators('pgsql (no ltree)');
        }

        if ($candidates === []) {
            return null;
        }

        $arrayLiteral = $this->buildLtreeArray($candidates);

        $result = DB::selectOne(
            "SELECT (ARRAY[{$arrayLiteral}]::ltree[] ?@> ?::ltree)::text as result",
            [$path]
        );

        return $result?->result;
    }

    public function firstDescendantFrom(string $path, array $candidates): ?string
    {
        if (! $this->supportsArrayOperators()) {
            throw UnsupportedDatabaseException::arrayOperators('pgsql (no ltree)');
        }

        if ($candidates === []) {
            return null;
        }

        $arrayLiteral = $this->buildLtreeArray($candidates);

        $result = DB::selectOne(
            "SELECT (ARRAY[{$arrayLiteral}]::ltree[] ?<@ ?::ltree)::text as result",
            [$path]
        );

        return $result?->result;
    }

    /**
     * Build PostgreSQL ltree array literal.
     *
     * @param  array<int, string>  $paths
     */
    protected function buildLtreeArray(array $paths): string
    {
        $escaped = array_map(fn (string $p) => "'".addslashes($p)."'", $paths);

        return implode(',', $escaped);
    }
}
