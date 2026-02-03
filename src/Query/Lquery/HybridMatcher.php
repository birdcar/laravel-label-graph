<?php

declare(strict_types=1);

namespace Birdcar\LabelTree\Query\Lquery;

use Illuminate\Support\Collection;

/**
 * Post-filters results for lquery patterns that can't be fully expressed in SQL/regex.
 *
 * Used when patterns contain modifier combinations (like %*) that require
 * semantic analysis beyond regex capabilities.
 */
final class HybridMatcher
{
    /**
     * Filter collection of paths by lquery pattern.
     *
     * @param  Collection<int, string>|array<int, string>  $paths
     * @return Collection<int, string>
     */
    public function filter(array|Collection $paths, string $pattern): Collection
    {
        $paths = collect($paths);

        return $paths->filter(fn (string $path) => Lquery::matches($pattern, $path));
    }

    /**
     * Check if a pattern requires hybrid matching.
     *
     * @param  array<int, Token>  $tokens
     */
    public static function needsHybrid(array $tokens): bool
    {
        foreach ($tokens as $token) {
            if ($token->needsPostFilter()) {
                return true;
            }
        }

        return false;
    }
}
