<?php

declare(strict_types=1);

namespace Birdcar\LabelGraph\Query\Lquery;

/**
 * Compiles lquery tokens to a regular expression.
 *
 * Used for databases without native ltree support (MySQL, SQLite, Postgres without ltree).
 *
 * The main challenge is handling star patterns that match zero labels,
 * since they affect the dot boundaries between segments.
 */
final class RegexCompiler
{
    private const LABEL_CHAR = '[A-Za-z0-9_-]';

    private const LABEL_PATTERN = '[A-Za-z0-9_-]+';

    /**
     * Compile tokens to a regex pattern.
     *
     * @param  array<int, Token>  $tokens
     */
    public function compile(array $tokens): string
    {
        if ($tokens === []) {
            return '^$';
        }

        // Build the regex by processing each token with awareness of position
        // Track whether previous token was a "greedy" star that absorbs the boundary dot
        $parts = [];
        $count = count($tokens);
        $prevAbsorbsTrailingDot = false;

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            $isFirst = $i === 0;
            $isLast = $i === $count - 1;

            // Effective "first" position: either truly first, or after a zero-star
            $needsLeadingDot = ! $isFirst && ! $prevAbsorbsTrailingDot;

            [$part, $absorbsTrailingDot] = $this->compileToken($token, $needsLeadingDot, $isLast);
            $parts[] = $part;
            $prevAbsorbsTrailingDot = $absorbsTrailingDot;
        }

        // Join parts - each part handles its own dot prefix/suffix logic
        $pattern = implode('', $parts);

        return "^{$pattern}$";
    }

    /**
     * Compile a single token with position awareness.
     *
     * @return array{0: string, 1: bool} [pattern, absorbsTrailingDot]
     */
    protected function compileToken(Token $token, bool $needsLeadingDot, bool $isLast): array
    {
        return match ($token->type) {
            Token::TYPE_STAR => $this->compileStar($token, $needsLeadingDot, $isLast),
            Token::TYPE_LABEL => [$this->compileLabel($token, $needsLeadingDot), false],
            Token::TYPE_GROUP => [$this->compileGroup($token, $needsLeadingDot), false],
            default => throw new \InvalidArgumentException("Unknown token type: {$token->type}"),
        };
    }

    /**
     * Compile a star token (matches zero or more labels).
     *
     * @return array{0: string, 1: bool} [pattern, absorbsTrailingDot]
     */
    protected function compileStar(Token $token, bool $needsLeadingDot, bool $isLast): array
    {
        $min = $token->getEffectiveMin();
        $max = $token->getEffectiveMax();

        // Generate pattern for matching N labels (with internal dots)
        $labelWithDot = self::LABEL_PATTERN.'\.';
        $label = self::LABEL_PATTERN;

        // Star can match zero labels - it absorbs the trailing dot for the next token
        $canMatchZero = $min === 0;

        if ($min === 0 && $max === null) {
            // Zero or more labels
            if (! $needsLeadingDot && $isLast) {
                // Pattern is just * - matches any path (one or more labels)
                return ['.+', false];
            }
            if (! $needsLeadingDot) {
                // *.something - zero or more labels at start
                // Pattern includes trailing dot: (label.)* matches "" or "a." or "a.b."
                return ['(?:'.$labelWithDot.')*', true];
            }
            if ($isLast) {
                // something.* - zero or more labels at end
                return ['(?:\.'.$label.')*', false];
            }

            // Middle: something.*.other
            // We need to match: nothing (adjacent tokens touch), or .label, or .label.label...
            // And include trailing dot for next token
            return ['(?:\.'.$label.')*(?:\.)?', false];
        }

        if ($min === 1 && $max === 1) {
            // Exactly one label
            $prefix = $needsLeadingDot ? '\.' : '';

            return [$prefix.$label, false];
        }

        if ($max !== null && $min === $max) {
            // Exactly n labels
            // When n=0, we match nothing, so next token is effectively "first" (absorb the dot)
            $absorbsDot = ($min === 0 && ! $needsLeadingDot);

            return [$this->buildExactLabels($min, $needsLeadingDot), $absorbsDot];
        }

        if ($max === null) {
            // At least n labels
            return [$this->buildMinLabels($min, $needsLeadingDot), false];
        }

        // Between min and max labels
        return [$this->buildRangeLabels($min, $max, $needsLeadingDot, $isLast), $canMatchZero];
    }

    /**
     * Build pattern for exactly n labels.
     */
    protected function buildExactLabels(int $n, bool $needsLeadingDot): string
    {
        if ($n === 0) {
            return '';
        }

        $label = self::LABEL_PATTERN;
        $prefix = $needsLeadingDot ? '\.' : '';

        if ($n === 1) {
            return $prefix.$label;
        }

        // n labels with dots between: label\.label\.label (n-1 internal dots)
        return $prefix.$label.str_repeat('\\.'.self::LABEL_PATTERN, $n - 1);
    }

    /**
     * Build pattern for at least n labels.
     */
    protected function buildMinLabels(int $min, bool $needsLeadingDot): string
    {
        $label = self::LABEL_PATTERN;

        if ($min === 0) {
            // Zero or more
            if (! $needsLeadingDot) {
                return '(?:'.$label.'\\.)*';
            }

            return '(?:\\.'.$label.')*';
        }

        // At least min labels, followed by zero or more
        $exact = $this->buildExactLabels($min, $needsLeadingDot);

        return $exact.'(?:\\.'.$label.')*';
    }

    /**
     * Build pattern for between min and max labels.
     */
    protected function buildRangeLabels(int $min, int $max, bool $needsLeadingDot, bool $isLast): string
    {
        $label = self::LABEL_PATTERN;

        if ($min === 0) {
            // 0 to max labels - special handling for boundaries
            if ($max === 0) {
                return '';
            }
            if ($max === 1) {
                if (! $needsLeadingDot && ! $isLast) {
                    // At start, not at end: include trailing dot
                    return '(?:'.$label.'\\.)?';
                }

                return $needsLeadingDot ? '(?:\\.'.$label.')?' : '(?:'.$label.')?';
            }
            // Up to max labels
            if (! $needsLeadingDot && ! $isLast) {
                // At start, include trailing dot
                return '(?:'.$label.'(?:\\.'.$label.'){0,'.($max - 1).'}\\.)?';
            }
            if (! $needsLeadingDot) {
                return '(?:'.$label.'(?:\\.'.$label.'){0,'.($max - 1).'})?';
            }

            return '(?:\\.'.$label.'(?:\\.'.$label.'){0,'.($max - 1).'})?';
        }

        // min to max labels
        $exact = $this->buildExactLabels($min, $needsLeadingDot);
        $optional = $max - $min;

        return $exact.'(?:\\.'.$label.'){0,'.$optional.'}';
    }

    /**
     * Compile a label token.
     */
    protected function compileLabel(Token $token, bool $needsLeadingDot): string
    {
        $prefix = $needsLeadingDot ? '\.' : '';
        $value = preg_quote($token->value ?? '', '/');
        $pattern = $value;

        // Handle modifiers
        if ($token->caseInsensitive) {
            $pattern = '(?i:'.$pattern.')';
        }

        if ($token->prefixMatch) {
            $pattern .= self::LABEL_CHAR.'*';
        }

        if ($token->wordMatch) {
            $pattern .= '(?:_'.self::LABEL_CHAR.'+)*';
        }

        // Handle quantifier (labels can also have quantifiers)
        $min = $token->getEffectiveMin();
        $max = $token->getEffectiveMax();

        if ($min === 1 && $max === 1) {
            return $prefix.$pattern;
        }

        return $this->applyLabelQuantifier($pattern, $min, $max, $needsLeadingDot);
    }

    /**
     * Compile a group token (OR / NOT).
     */
    protected function compileGroup(Token $token, bool $needsLeadingDot): string
    {
        $prefix = $needsLeadingDot ? '\.' : '';
        $alternatives = [];

        foreach ($token->alternatives as $alt) {
            $pattern = preg_quote($alt['value'], '/');

            if ($alt['caseInsensitive']) {
                $pattern = '(?i:'.$pattern.')';
            }

            if ($alt['prefixMatch']) {
                $pattern .= self::LABEL_CHAR.'*';
            }

            if ($alt['wordMatch']) {
                $pattern .= '(?:_'.self::LABEL_CHAR.'+)*';
            }

            $alternatives[] = $pattern;
        }

        if ($token->negated) {
            // NOT: match any label that doesn't match the alternatives
            $negativeAlt = implode('|', $alternatives);
            $pattern = '(?!(?:'.$negativeAlt.')(?:\\.|$))'.self::LABEL_PATTERN;
        } else {
            $pattern = '(?:'.implode('|', $alternatives).')';
        }

        // Apply quantifier
        $min = $token->getEffectiveMin();
        $max = $token->getEffectiveMax();

        if ($min === 1 && $max === 1) {
            return $prefix.$pattern;
        }

        return $this->applyLabelQuantifier($pattern, $min, $max, $needsLeadingDot);
    }

    /**
     * Apply a quantifier to a label/group pattern.
     */
    protected function applyLabelQuantifier(string $pattern, int $min, ?int $max, bool $needsLeadingDot): string
    {
        $prefix = $needsLeadingDot ? '\.' : '';

        if ($min === 0 && $max === null) {
            // Zero or more of this pattern
            if (! $needsLeadingDot) {
                return '(?:'.$pattern.'(?:\\.'.$pattern.')*)?';
            }

            return '(?:\\.'.$pattern.')*';
        }

        if ($min === 1 && $max === null) {
            // One or more
            return $prefix.$pattern.'(?:\\.'.$pattern.')*';
        }

        if ($min === $max) {
            // Exactly n
            if ($min === 0) {
                return '';
            }

            return $prefix.$pattern.str_repeat('\\.'.$pattern, $min - 1);
        }

        // Range: min to max
        if ($min === 0) {
            if (! $needsLeadingDot) {
                return '(?:'.$pattern.'(?:\\.'.$pattern.'){0,'.($max - 1).'})?';
            }

            return '(?:\\.'.$pattern.'){0,'.$max.'}';
        }

        $required = $prefix.$pattern.str_repeat('\\.'.$pattern, $min - 1);
        $optional = $max - $min;

        return $required.'(?:\\.'.$pattern.'){0,'.$optional.'}';
    }

    /**
     * Compile a "loose" regex that over-matches (ignores % word-match semantics).
     *
     * Used by HybridMatcher to get a broader result set that's then filtered in PHP.
     *
     * @param  array<int, Token>  $tokens
     */
    public function compileLoose(array $tokens): string
    {
        if ($tokens === []) {
            return '^$';
        }

        // Build the regex by processing each token with awareness of position
        $parts = [];
        $count = count($tokens);
        $prevAbsorbsTrailingDot = false;

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            $isFirst = $i === 0;
            $isLast = $i === $count - 1;

            $needsLeadingDot = ! $isFirst && ! $prevAbsorbsTrailingDot;

            [$part, $absorbsTrailingDot] = $this->compileTokenLoose($token, $needsLeadingDot, $isLast);
            $parts[] = $part;
            $prevAbsorbsTrailingDot = $absorbsTrailingDot;
        }

        $pattern = implode('', $parts);

        return "^{$pattern}$";
    }

    /**
     * Compile a single token in "loose" mode (ignores wordMatch semantics).
     *
     * @return array{0: string, 1: bool} [pattern, absorbsTrailingDot]
     */
    protected function compileTokenLoose(Token $token, bool $needsLeadingDot, bool $isLast): array
    {
        return match ($token->type) {
            Token::TYPE_STAR => $this->compileStar($token, $needsLeadingDot, $isLast),
            Token::TYPE_LABEL => [$this->compileLabelLoose($token, $needsLeadingDot), false],
            Token::TYPE_GROUP => [$this->compileGroupLoose($token, $needsLeadingDot), false],
            default => throw new \InvalidArgumentException("Unknown token type: {$token->type}"),
        };
    }

    /**
     * Compile a label token in "loose" mode.
     */
    protected function compileLabelLoose(Token $token, bool $needsLeadingDot): string
    {
        $prefix = $needsLeadingDot ? '\.' : '';
        $value = preg_quote($token->value ?? '', '/');
        $pattern = $value;

        if ($token->caseInsensitive) {
            $pattern = '(?i:'.$pattern.')';
        }

        // In loose mode, treat both prefix and wordMatch as "any suffix allowed"
        if ($token->prefixMatch || $token->wordMatch) {
            $pattern .= self::LABEL_CHAR.'*';
        }

        $min = $token->getEffectiveMin();
        $max = $token->getEffectiveMax();

        if ($min === 1 && $max === 1) {
            return $prefix.$pattern;
        }

        return $this->applyLabelQuantifier($pattern, $min, $max, $needsLeadingDot);
    }

    /**
     * Compile a group token in "loose" mode.
     */
    protected function compileGroupLoose(Token $token, bool $needsLeadingDot): string
    {
        $prefix = $needsLeadingDot ? '\.' : '';
        $alternatives = [];

        foreach ($token->alternatives as $alt) {
            $pattern = preg_quote($alt['value'], '/');

            if ($alt['caseInsensitive']) {
                $pattern = '(?i:'.$pattern.')';
            }

            // In loose mode, treat both prefix and wordMatch as "any suffix allowed"
            if ($alt['prefixMatch'] || $alt['wordMatch']) {
                $pattern .= self::LABEL_CHAR.'*';
            }

            $alternatives[] = $pattern;
        }

        if ($token->negated) {
            $negativeAlt = implode('|', $alternatives);
            $pattern = '(?!(?:'.$negativeAlt.')(?:\\.|$))'.self::LABEL_PATTERN;
        } else {
            $pattern = '(?:'.implode('|', $alternatives).')';
        }

        $min = $token->getEffectiveMin();
        $max = $token->getEffectiveMax();

        if ($min === 1 && $max === 1) {
            return $prefix.$pattern;
        }

        return $this->applyLabelQuantifier($pattern, $min, $max, $needsLeadingDot);
    }
}
