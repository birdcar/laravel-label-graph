<?php

declare(strict_types=1);

namespace Birdcar\LabelGraph\Query\Lquery;

use Birdcar\LabelGraph\Exceptions\LqueryParseException;

/**
 * Parses lquery patterns into a sequence of tokens.
 *
 * Lquery syntax (PostgreSQL ltree):
 * - Patterns are dot-separated elements
 * - `*` matches zero or more labels (default quantifier {,})
 * - Labels match exactly (with optional modifiers)
 * - Modifiers: @ (case-insensitive), * (prefix), % (underscore words)
 * - Groups: label1|label2 (OR), !label1|label2 (NOT)
 * - Quantifiers: {n}, {n,}, {n,m}, {,m}, {,}
 */
final class Parser
{
    /**
     * Parse an lquery pattern into tokens.
     *
     * @return array<int, Token>
     */
    public function parse(string $pattern): array
    {
        if ($pattern === '') {
            throw LqueryParseException::emptyPattern();
        }

        $elements = explode('.', $pattern);
        $tokens = [];

        foreach ($elements as $element) {
            $tokens[] = $this->parseElement($element);
        }

        return $tokens;
    }

    /**
     * Parse a single element (between dots).
     */
    protected function parseElement(string $element): Token
    {
        if ($element === '') {
            throw LqueryParseException::emptyElement();
        }

        // Check for star (with optional quantifier)
        if ($element === '*' || str_starts_with($element, '*{')) {
            return $this->parseStar($element);
        }

        // Check for group (contains | or starts with !)
        if (str_contains($element, '|') || str_starts_with($element, '!')) {
            return $this->parseGroup($element);
        }

        // Simple label with optional modifiers and quantifier
        return $this->parseLabel($element);
    }

    /**
     * Parse a star element: *, *{n}, *{n,}, *{n,m}, *{,m}, *{,}
     */
    protected function parseStar(string $element): Token
    {
        if ($element === '*') {
            // Default: zero or more
            return Token::star(0, null);
        }

        // Extract quantifier: *{...}
        if (preg_match('/^\*\{([^}]+)\}$/', $element, $matches)) {
            $quantifier = '{'.$matches[1].'}';
            [$min, $max] = $this->parseQuantifier($quantifier);

            // For star tokens, null min defaults to 0
            return Token::star($min ?? 0, $max);
        }

        throw LqueryParseException::invalidQuantifier($element);
    }

    /**
     * Parse a simple label: label, label@, label*, label%, label{n,m}, etc.
     */
    protected function parseLabel(string $element): Token
    {
        // Extract quantifier if present
        $quantifier = null;
        if (preg_match('/^(.+?)(\{[^}]+\})$/', $element, $matches)) {
            $element = $matches[1];
            $quantifier = $matches[2];
        }

        // Extract modifiers (can be combined: label@*%)
        $caseInsensitive = false;
        $prefixMatch = false;
        $wordMatch = false;

        // Process modifiers from end of string
        while (strlen($element) > 0) {
            $lastChar = substr($element, -1);
            if ($lastChar === '@') {
                $caseInsensitive = true;
                $element = substr($element, 0, -1);
            } elseif ($lastChar === '*') {
                $prefixMatch = true;
                $element = substr($element, 0, -1);
            } elseif ($lastChar === '%') {
                $wordMatch = true;
                $element = substr($element, 0, -1);
            } else {
                break;
            }
        }

        if ($element === '') {
            throw LqueryParseException::invalidLabel('(empty)');
        }

        // Validate label characters (alphanumeric, underscore, hyphen)
        if (! preg_match('/^[A-Za-z0-9_-]+$/', $element)) {
            throw LqueryParseException::invalidLabel($element);
        }

        [$min, $max] = $this->parseQuantifier($quantifier);

        return Token::label($element, $caseInsensitive, $prefixMatch, $wordMatch, $min, $max);
    }

    /**
     * Parse a group: !label1|label2{n,m} or label1|label2|label3{n,m}
     */
    protected function parseGroup(string $element): Token
    {
        $negated = false;
        if (str_starts_with($element, '!')) {
            $negated = true;
            $element = substr($element, 1);
        }

        // Extract quantifier if present
        $quantifier = null;
        if (preg_match('/^(.+?)(\{[^}]+\})$/', $element, $matches)) {
            $element = $matches[1];
            $quantifier = $matches[2];
        }

        // Split by | to get alternatives
        $parts = explode('|', $element);
        $alternatives = [];

        foreach ($parts as $part) {
            $alternatives[] = $this->parseLabelModifiers($part);
        }

        [$min, $max] = $this->parseQuantifier($quantifier);

        return Token::group($alternatives, $negated, $min, $max);
    }

    /**
     * Parse a label with modifiers (for use in groups).
     *
     * @return array{value: string, caseInsensitive: bool, prefixMatch: bool, wordMatch: bool}
     */
    protected function parseLabelModifiers(string $label): array
    {
        $caseInsensitive = false;
        $prefixMatch = false;
        $wordMatch = false;

        while (strlen($label) > 0) {
            $lastChar = substr($label, -1);
            if ($lastChar === '@') {
                $caseInsensitive = true;
                $label = substr($label, 0, -1);
            } elseif ($lastChar === '*') {
                $prefixMatch = true;
                $label = substr($label, 0, -1);
            } elseif ($lastChar === '%') {
                $wordMatch = true;
                $label = substr($label, 0, -1);
            } else {
                break;
            }
        }

        if ($label === '' || ! preg_match('/^[A-Za-z0-9_-]+$/', $label)) {
            throw LqueryParseException::invalidLabel($label ?: '(empty)');
        }

        return [
            'value' => $label,
            'caseInsensitive' => $caseInsensitive,
            'prefixMatch' => $prefixMatch,
            'wordMatch' => $wordMatch,
        ];
    }

    /**
     * Parse a quantifier string: {n}, {n,}, {n,m}, {,m}, {,}
     *
     * @return array{0: int|null, 1: int|null}
     */
    protected function parseQuantifier(?string $quantifier): array
    {
        if ($quantifier === null) {
            return [null, null];
        }

        // Remove braces
        $inner = substr($quantifier, 1, -1);

        // Handle {,} - any number
        if ($inner === ',') {
            return [0, null];
        }

        // Handle {n} - exactly n
        if (is_numeric($inner)) {
            $n = (int) $inner;

            return [$n, $n];
        }

        // Handle {n,}, {n,m}, {,m}
        if (str_contains($inner, ',')) {
            $parts = explode(',', $inner);

            return $this->parseQuantifierParts($parts[0], $parts[1] ?? null);
        }

        throw LqueryParseException::invalidQuantifier($quantifier);
    }

    /**
     * Parse quantifier parts (min, max).
     *
     * @return array{0: int, 1: int|null}
     */
    protected function parseQuantifierParts(string $minPart, ?string $maxPart): array
    {
        $min = $minPart === '' ? 0 : (int) $minPart;
        $max = ($maxPart === null || $maxPart === '') ? null : (int) $maxPart;

        return [$min, $max];
    }
}
