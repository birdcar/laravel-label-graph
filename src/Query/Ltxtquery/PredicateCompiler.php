<?php

declare(strict_types=1);

namespace Birdcar\LabelGraph\Query\Ltxtquery;

use Closure;
use InvalidArgumentException;

/**
 * Compiles AST to a PHP predicate function.
 *
 * Used for databases without native ltxtquery support (MySQL, SQLite, Postgres without ltree).
 */
final class PredicateCompiler
{
    /**
     * Compile AST to a predicate function.
     *
     * @return Closure(string): bool
     */
    public function compile(Token $ast): Closure
    {
        return $this->compileNode($ast);
    }

    /**
     * @return Closure(string): bool
     */
    private function compileNode(Token $node): Closure
    {
        return match ($node->type) {
            Token::TYPE_WORD => $this->compileWord($node),
            Token::TYPE_AND => $this->compileAnd($node),
            Token::TYPE_OR => $this->compileOr($node),
            Token::TYPE_NOT => $this->compileNot($node),
            Token::TYPE_GROUP => $this->compileNode($node->children[0]),
            default => throw new InvalidArgumentException("Unknown token type: {$node->type}"),
        };
    }

    /**
     * @return Closure(string): bool
     */
    private function compileWord(Token $node): Closure
    {
        $value = $node->value ?? '';
        $caseInsensitive = $node->caseInsensitive;
        $prefixMatch = $node->prefixMatch;
        $wordMatch = $node->wordMatch;

        return function (string $path) use ($value, $caseInsensitive, $prefixMatch, $wordMatch): bool {
            $labels = explode('.', $path);

            foreach ($labels as $label) {
                if ($this->matchLabel($label, $value, $caseInsensitive, $prefixMatch, $wordMatch)) {
                    return true;
                }
            }

            return false;
        };
    }

    private function matchLabel(
        string $label,
        string $value,
        bool $caseInsensitive,
        bool $prefixMatch,
        bool $wordMatch,
    ): bool {
        $compareLabel = $caseInsensitive ? strtolower($label) : $label;
        $compareValue = $caseInsensitive ? strtolower($value) : $value;

        if ($wordMatch) {
            // Match underscore-separated words
            $words = explode('_', $compareLabel);
            $valueWords = explode('_', $compareValue);

            if (count($words) < count($valueWords)) {
                return false;
            }

            for ($i = 0; $i < count($valueWords); $i++) {
                $labelWord = $words[$i];
                $valueWord = $valueWords[$i];

                if ($prefixMatch) {
                    if (! str_starts_with($labelWord, $valueWord)) {
                        return false;
                    }
                } else {
                    if ($labelWord !== $valueWord) {
                        return false;
                    }
                }
            }

            return true;
        }

        if ($prefixMatch) {
            return str_starts_with($compareLabel, $compareValue);
        }

        return $compareLabel === $compareValue;
    }

    /**
     * @return Closure(string): bool
     */
    private function compileAnd(Token $node): Closure
    {
        $left = $this->compileNode($node->children[0]);
        $right = $this->compileNode($node->children[1]);

        return fn (string $path): bool => $left($path) && $right($path);
    }

    /**
     * @return Closure(string): bool
     */
    private function compileOr(Token $node): Closure
    {
        $left = $this->compileNode($node->children[0]);
        $right = $this->compileNode($node->children[1]);

        return fn (string $path): bool => $left($path) || $right($path);
    }

    /**
     * @return Closure(string): bool
     */
    private function compileNot(Token $node): Closure
    {
        $inner = $this->compileNode($node->children[0]);

        return fn (string $path): bool => ! $inner($path);
    }
}
