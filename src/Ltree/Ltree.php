<?php

declare(strict_types=1);

namespace Birdcar\LabelGraph\Ltree;

use Birdcar\LabelGraph\Exceptions\InvalidPathException;
use Illuminate\Support\Collection;

/**
 * Static helpers for ltree path manipulation.
 *
 * All methods work on dot-separated path strings and match
 * PostgreSQL ltree function semantics.
 */
final class Ltree
{
    /**
     * Get number of labels in path.
     */
    public static function nlevel(string $path): int
    {
        if ($path === '') {
            return 0;
        }

        return substr_count($path, '.') + 1;
    }

    /**
     * Extract subpath starting at offset with optional length.
     *
     * @param  int  $offset  Start position (0-indexed). Negative counts from end.
     * @param  int|null  $len  Number of labels to include. Negative omits from end. Null = to end.
     */
    public static function subpath(string $path, int $offset, ?int $len = null): string
    {
        if ($path === '') {
            return '';
        }

        $labels = explode('.', $path);
        $count = count($labels);

        // Handle negative offset (count from end)
        if ($offset < 0) {
            $offset = max(0, $count + $offset);
        }

        // Handle out of bounds
        if ($offset >= $count) {
            return '';
        }

        // Handle length
        if ($len === null) {
            $slice = array_slice($labels, $offset);
        } elseif ($len < 0) {
            // Negative length: omit from end
            $endOffset = $count + $len;
            if ($endOffset <= $offset) {
                return '';
            }
            $slice = array_slice($labels, $offset, $endOffset - $offset);
        } else {
            $slice = array_slice($labels, $offset, $len);
        }

        return implode('.', $slice);
    }

    /**
     * Extract subpath from start to end position (exclusive).
     *
     * PostgreSQL-compatible: subltree('a.b.c.d', 1, 3) = 'b.c'
     */
    public static function subltree(string $path, int $start, int $end): string
    {
        if ($start >= $end || $path === '') {
            return '';
        }

        return self::subpath($path, $start, $end - $start);
    }

    /**
     * Find position of subpath within path.
     *
     * @param  int  $offset  Start search at this position. Negative counts from end.
     * @return int Position (0-indexed), or -1 if not found.
     */
    public static function index(string $path, string $subpath, int $offset = 0): int
    {
        if ($path === '' || $subpath === '') {
            return -1;
        }

        $pathLabels = explode('.', $path);
        $subLabels = explode('.', $subpath);
        $pathCount = count($pathLabels);
        $subCount = count($subLabels);

        if ($subCount > $pathCount) {
            return -1;
        }

        // Handle negative offset
        if ($offset < 0) {
            $offset = max(0, $pathCount + $offset);
        }

        $maxStart = $pathCount - $subCount;

        for ($i = $offset; $i <= $maxStart; $i++) {
            $match = true;
            for ($j = 0; $j < $subCount; $j++) {
                if ($pathLabels[$i + $j] !== $subLabels[$j]) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                return $i;
            }
        }

        return -1;
    }

    /**
     * Compute longest common ancestor of paths.
     *
     * @param  array<int, string>|Collection<int, string>  $paths
     */
    public static function lca(array|Collection $paths): string
    {
        $paths = collect($paths)->filter(fn ($p) => $p !== '')->values();

        if ($paths->isEmpty()) {
            return '';
        }

        if ($paths->count() === 1) {
            return $paths->first();
        }

        // Split all paths into label arrays
        $labelArrays = $paths->map(fn (string $p) => explode('.', $p))->all();

        if ($labelArrays === []) {
            return '';
        }

        // Find minimum length
        $minLen = min(array_map('count', $labelArrays));

        // Find common prefix length
        $commonLen = 0;
        for ($i = 0; $i < $minLen; $i++) {
            $label = $labelArrays[0][$i];
            $allMatch = true;

            foreach ($labelArrays as $labels) {
                if ($labels[$i] !== $label) {
                    $allMatch = false;
                    break;
                }
            }

            if (! $allMatch) {
                break;
            }

            $commonLen++;
        }

        if ($commonLen === 0) {
            return '';
        }

        return implode('.', array_slice($labelArrays[0], 0, $commonLen));
    }

    /**
     * Convert text to ltree path (validates format).
     *
     * @throws InvalidPathException
     */
    public static function text2ltree(string $text): string
    {
        // Trim whitespace
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        // Validate format: labels separated by single dots
        if (str_contains($text, '..')) {
            throw InvalidPathException::consecutiveDots($text);
        }

        if (str_starts_with($text, '.') || str_ends_with($text, '.')) {
            throw InvalidPathException::invalidBoundary($text);
        }

        // Validate each label
        $labels = explode('.', $text);
        foreach ($labels as $label) {
            if (! preg_match('/^[A-Za-z0-9_-]+$/', $label)) {
                throw InvalidPathException::invalidLabel($label, $text);
            }
        }

        return $text;
    }

    /**
     * Convert ltree to text (identity for string paths).
     */
    public static function ltree2text(string $path): string
    {
        return $path;
    }

    /**
     * Concatenate paths.
     */
    public static function concat(string $path1, string $path2): string
    {
        if ($path1 === '') {
            return $path2;
        }
        if ($path2 === '') {
            return $path1;
        }

        return $path1.'.'.$path2;
    }
}
