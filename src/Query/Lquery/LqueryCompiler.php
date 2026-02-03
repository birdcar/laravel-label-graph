<?php

declare(strict_types=1);

namespace Birdcar\LabelGraph\Query\Lquery;

/**
 * Compiles lquery tokens to native PostgreSQL lquery syntax.
 *
 * Used when PostgreSQL ltree extension is available.
 */
final class LqueryCompiler
{
    /**
     * Compile tokens to native lquery string.
     *
     * @param  array<int, Token>  $tokens
     */
    public function compile(array $tokens): string
    {
        if ($tokens === []) {
            return '';
        }

        $parts = [];

        foreach ($tokens as $token) {
            $parts[] = $this->compileToken($token);
        }

        return implode('.', $parts);
    }

    /**
     * Compile a single token to lquery syntax.
     */
    protected function compileToken(Token $token): string
    {
        return match ($token->type) {
            Token::TYPE_STAR => $this->compileStar($token),
            Token::TYPE_LABEL => $this->compileLabel($token),
            Token::TYPE_GROUP => $this->compileGroup($token),
            default => throw new \InvalidArgumentException("Unknown token type: {$token->type}"),
        };
    }

    /**
     * Compile a star token.
     */
    protected function compileStar(Token $token): string
    {
        $quantifier = $this->buildQuantifier(
            $token->getEffectiveMin(),
            $token->getEffectiveMax()
        );

        return '*'.$quantifier;
    }

    /**
     * Compile a label token.
     */
    protected function compileLabel(Token $token): string
    {
        $result = $token->value ?? '';

        // Modifiers come after the label
        if ($token->prefixMatch) {
            $result .= '*';
        }
        if ($token->caseInsensitive) {
            $result .= '@';
        }
        if ($token->wordMatch) {
            $result .= '%';
        }

        // Quantifier comes last
        $quantifier = $this->buildQuantifier(
            $token->getEffectiveMin(),
            $token->getEffectiveMax()
        );

        // Only add quantifier if not default (1,1 for labels)
        if ($quantifier !== '' && $quantifier !== '{1}') {
            $result .= $quantifier;
        }

        return $result;
    }

    /**
     * Compile a group token (OR / NOT).
     */
    protected function compileGroup(Token $token): string
    {
        $parts = [];

        foreach ($token->alternatives as $alt) {
            $part = $alt['value'];

            if ($alt['prefixMatch']) {
                $part .= '*';
            }
            if ($alt['caseInsensitive']) {
                $part .= '@';
            }
            if ($alt['wordMatch']) {
                $part .= '%';
            }

            $parts[] = $part;
        }

        $result = implode('|', $parts);

        if ($token->negated) {
            $result = '!'.$result;
        }

        // Quantifier comes last
        $quantifier = $this->buildQuantifier(
            $token->getEffectiveMin(),
            $token->getEffectiveMax()
        );

        if ($quantifier !== '' && $quantifier !== '{1}') {
            $result .= $quantifier;
        }

        return $result;
    }

    /**
     * Build a quantifier string.
     */
    protected function buildQuantifier(int $min, ?int $max): string
    {
        // Default for star is {,} (0 to unlimited) - don't output
        // Default for labels is {1} (exactly 1) - don't output

        if ($min === 0 && $max === null) {
            // This is the default for star, but explicit {,} is valid
            return '{,}';
        }

        if ($min === $max) {
            return '{'.$min.'}';
        }

        if ($max === null) {
            return '{'.$min.',}';
        }

        if ($min === 0) {
            return '{,'.$max.'}';
        }

        return '{'.$min.','.$max.'}';
    }
}
