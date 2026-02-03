<?php

declare(strict_types=1);

namespace Birdcar\LabelGraph\Query\Lquery;

use Illuminate\Support\Collection;

/**
 * Main entry point for lquery pattern compilation.
 *
 * Handles parsing lquery patterns and compiling them to either:
 * - Native PostgreSQL lquery syntax (when ltree extension is available)
 * - Regular expressions (for MySQL, SQLite, and Postgres without ltree)
 */
final class Lquery
{
    private static ?Parser $parser = null;

    private static ?RegexCompiler $regexCompiler = null;

    private static ?LqueryCompiler $lqueryCompiler = null;

    /**
     * Compile an lquery pattern to a regular expression.
     */
    public static function toRegex(string $pattern): string
    {
        $tokens = self::getParser()->parse($pattern);

        return self::getRegexCompiler()->compile($tokens);
    }

    /**
     * Compile an lquery pattern to native PostgreSQL lquery syntax.
     *
     * This normalizes the pattern through our parser, ensuring consistent behavior.
     */
    public static function toLquery(string $pattern): string
    {
        $tokens = self::getParser()->parse($pattern);

        return self::getLqueryCompiler()->compile($tokens);
    }

    /**
     * Parse an lquery pattern into tokens.
     *
     * @return array<int, Token>
     */
    public static function parse(string $pattern): array
    {
        return self::getParser()->parse($pattern);
    }

    /**
     * Validate an lquery pattern.
     *
     * Returns null if valid, or an error message if invalid.
     */
    public static function validate(string $pattern): ?string
    {
        try {
            self::getParser()->parse($pattern);

            return null;
        } catch (\InvalidArgumentException $e) {
            return $e->getMessage();
        }
    }

    /**
     * Check if a path matches an lquery pattern using regex.
     *
     * This is useful for testing and for in-memory filtering.
     */
    public static function matches(string $pattern, string $path): bool
    {
        $regex = self::toRegex($pattern);

        return preg_match('/'.$regex.'/', $path) === 1;
    }

    /**
     * Check if a pattern requires hybrid matching (regex + PHP post-filter).
     */
    public static function needsHybridMatch(string $pattern): bool
    {
        $tokens = self::getParser()->parse($pattern);

        return HybridMatcher::needsHybrid($tokens);
    }

    /**
     * Filter paths using hybrid matching (for patterns that can't be fully expressed in regex).
     *
     * @param  iterable<int, string>  $paths
     * @return Collection<int, string>
     */
    public static function hybridFilter(iterable $paths, string $pattern): Collection
    {
        return (new HybridMatcher)->filter(collect($paths), $pattern);
    }

    /**
     * Compile a "loose" regex that over-matches (for use with hybrid matching).
     */
    public static function toLooseRegex(string $pattern): string
    {
        $tokens = self::getParser()->parse($pattern);

        return self::getRegexCompiler()->compileLoose($tokens);
    }

    protected static function getParser(): Parser
    {
        return self::$parser ??= new Parser;
    }

    protected static function getRegexCompiler(): RegexCompiler
    {
        return self::$regexCompiler ??= new RegexCompiler;
    }

    protected static function getLqueryCompiler(): LqueryCompiler
    {
        return self::$lqueryCompiler ??= new LqueryCompiler;
    }
}
