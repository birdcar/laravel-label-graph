<?php

declare(strict_types=1);

namespace Birdcar\LabelTree\Query;

use Birdcar\LabelTree\Exceptions\UnsupportedDatabaseException;
use Birdcar\LabelTree\Query\Lquery\Lquery;
use Birdcar\LabelTree\Query\Ltxtquery\Ltxtquery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use PDO;

class SqliteAdapter implements PathQueryAdapter
{
    protected bool $regexpRegistered = false;

    protected bool $ltreeFunctionsRegistered = false;

    public function wherePathMatches(Builder $query, string $column, string $pattern): Builder
    {
        $this->ensureRegexpFunction($query);

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
     * Register a custom REGEXP function in SQLite.
     *
     * SQLite doesn't have REGEXP by default - it only recognizes the syntax
     * but throws an error unless you provide an implementation.
     */
    protected function ensureRegexpFunction(Builder $query): void
    {
        if ($this->regexpRegistered) {
            return;
        }

        /** @var \Illuminate\Database\Connection $connection */
        $connection = $query->getConnection();
        $pdo = $connection->getPdo();

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            // Register REGEXP function: returns 1 if value matches pattern, 0 otherwise
            $pdo->sqliteCreateFunction('regexp', function (string $pattern, string $value): int {
                return preg_match('/'.$pattern.'/', $value) === 1 ? 1 : 0;
            }, 2);
        }

        $this->regexpRegistered = true;
    }

    /**
     * Register ltree UDFs in SQLite.
     */
    public function ensureLtreeFunctions(Builder $query): void
    {
        if ($this->ltreeFunctionsRegistered) {
            return;
        }

        /** @var \Illuminate\Database\Connection $connection */
        $connection = $query->getConnection();
        $pdo = $connection->getPdo();

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            return;
        }

        // nlevel: count segments
        $pdo->sqliteCreateFunction('ltree_nlevel', function (?string $path): int {
            if ($path === null || $path === '') {
                return 0;
            }

            return substr_count($path, '.') + 1;
        }, 1);

        // subpath: extract subpath from offset
        $pdo->sqliteCreateFunction('ltree_subpath', function (?string $path, int $offset): string {
            if ($path === null || $path === '') {
                return '';
            }

            return \Birdcar\LabelTree\Ltree\Ltree::subpath($path, $offset);
        }, 2);

        // subpath with length: extract subpath from offset with length
        $pdo->sqliteCreateFunction('ltree_subpath_len', function (?string $path, int $offset, int $len): string {
            if ($path === null || $path === '') {
                return '';
            }

            return \Birdcar\LabelTree\Ltree\Ltree::subpath($path, $offset, $len);
        }, 3);

        // index: find subpath position
        $pdo->sqliteCreateFunction('ltree_index', function (?string $path, ?string $subpath): int {
            if ($path === null || $subpath === null) {
                return -1;
            }

            return \Birdcar\LabelTree\Ltree\Ltree::index($path, $subpath);
        }, 2);

        $this->ltreeFunctionsRegistered = true;
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
        throw UnsupportedDatabaseException::arrayOperators('sqlite');
    }

    public function wherePathHasDescendantIn(Builder $query, string $column, array $paths): Builder
    {
        throw UnsupportedDatabaseException::arrayOperators('sqlite');
    }

    public function whereAnyPathMatches(Builder $query, string $column, array $paths, string $pattern): Builder
    {
        throw UnsupportedDatabaseException::arrayOperators('sqlite');
    }

    public function firstAncestorFrom(string $path, array $candidates): ?string
    {
        throw UnsupportedDatabaseException::arrayOperators('sqlite');
    }

    public function firstDescendantFrom(string $path, array $candidates): ?string
    {
        throw UnsupportedDatabaseException::arrayOperators('sqlite');
    }
}
