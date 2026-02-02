<?php

declare(strict_types=1);

namespace Birdcar\LabelTree\Query\Ltxtquery;

use Closure;
use Illuminate\Support\Collection;

/**
 * Public API facade for ltxtquery operations.
 *
 * ltxtquery is a full-text-search-like pattern syntax that matches labels
 * regardless of their position in the path, using boolean expressions.
 *
 * Examples:
 *   - `Europe` matches paths containing label "Europe"
 *   - `Europe & Asia` matches paths containing both labels
 *   - `Europe | Asia` matches paths containing either label
 *   - `!Europe` matches paths NOT containing "Europe"
 *   - `(Europe | Asia) & !Africa` nested boolean
 *   - `Russia*` prefix matching
 *   - `russia@` case-insensitive
 */
final class Ltxtquery
{
    /**
     * Parse pattern and return AST.
     */
    public static function parse(string $pattern): Token
    {
        return (new Parser)->parse($pattern);
    }

    /**
     * Validate pattern without throwing.
     */
    public static function validate(string $pattern): bool
    {
        try {
            self::parse($pattern);

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Convert to native PostgreSQL ltxtquery string.
     */
    public static function toNative(string $pattern): string
    {
        $ast = self::parse($pattern);

        return (new LtxtqueryCompiler)->compile($ast);
    }

    /**
     * Get predicate function for filtering paths.
     *
     * @return Closure(string): bool
     */
    public static function toPredicate(string $pattern): Closure
    {
        $ast = self::parse($pattern);

        return (new PredicateCompiler)->compile($ast);
    }

    /**
     * Check if pattern matches path.
     */
    public static function matches(string $pattern, string $path): bool
    {
        $predicate = self::toPredicate($pattern);

        return $predicate($path);
    }

    /**
     * Filter paths by pattern.
     *
     * @param  iterable<int, string>  $paths
     * @return Collection<int, string>
     */
    public static function filter(iterable $paths, string $pattern): Collection
    {
        $predicate = self::toPredicate($pattern);

        return collect($paths)->filter($predicate)->values();
    }
}
