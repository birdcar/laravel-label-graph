<?php

declare(strict_types=1);

namespace Birdcar\LabelGraph\Query\Lquery;

/**
 * Represents a single element in an lquery pattern.
 */
final class Token
{
    public const TYPE_STAR = 'star';

    public const TYPE_LABEL = 'label';

    public const TYPE_GROUP = 'group';

    /**
     * @param  array<int, array{value: string, caseInsensitive: bool, prefixMatch: bool, wordMatch: bool}>  $alternatives  For TYPE_GROUP: list of label alternatives
     */
    public function __construct(
        public readonly string $type,
        public readonly ?string $value = null,
        public readonly ?int $quantifierMin = null,
        public readonly ?int $quantifierMax = null,
        public readonly bool $caseInsensitive = false,
        public readonly bool $prefixMatch = false,
        public readonly bool $wordMatch = false,
        public readonly bool $negated = false,
        public readonly array $alternatives = [],
    ) {}

    /**
     * Create a star token (matches labels).
     */
    public static function star(?int $min = null, ?int $max = null): self
    {
        return new self(
            type: self::TYPE_STAR,
            quantifierMin: $min,
            quantifierMax: $max,
        );
    }

    /**
     * Create a simple label token.
     */
    public static function label(
        string $value,
        bool $caseInsensitive = false,
        bool $prefixMatch = false,
        bool $wordMatch = false,
        ?int $min = null,
        ?int $max = null,
    ): self {
        return new self(
            type: self::TYPE_LABEL,
            value: $value,
            quantifierMin: $min,
            quantifierMax: $max,
            caseInsensitive: $caseInsensitive,
            prefixMatch: $prefixMatch,
            wordMatch: $wordMatch,
        );
    }

    /**
     * Create a group token (alternatives with OR, optional NOT).
     *
     * @param  array<int, array{value: string, caseInsensitive: bool, prefixMatch: bool, wordMatch: bool}>  $alternatives
     */
    public static function group(
        array $alternatives,
        bool $negated = false,
        ?int $min = null,
        ?int $max = null,
    ): self {
        return new self(
            type: self::TYPE_GROUP,
            quantifierMin: $min,
            quantifierMax: $max,
            negated: $negated,
            alternatives: $alternatives,
        );
    }

    /**
     * Check if this token has a quantifier.
     */
    public function hasQuantifier(): bool
    {
        return $this->quantifierMin !== null || $this->quantifierMax !== null;
    }

    /**
     * Get effective min (default 1 for labels, 0 for star).
     */
    public function getEffectiveMin(): int
    {
        if ($this->quantifierMin !== null) {
            return $this->quantifierMin;
        }

        // Default: star matches 0+, labels match exactly 1
        return $this->type === self::TYPE_STAR ? 0 : 1;
    }

    /**
     * Get effective max (null means unlimited).
     */
    public function getEffectiveMax(): ?int
    {
        if ($this->hasQuantifier()) {
            return $this->quantifierMax;
        }

        // Default: star matches 0+, labels match exactly 1
        return $this->type === self::TYPE_STAR ? null : 1;
    }

    /**
     * Check if this token requires PHP post-filtering for accurate matching.
     *
     * The % (word-match) modifier with prefix matching (*) has complex semantics
     * that can't be fully expressed in a single regex pass.
     */
    public function needsPostFilter(): bool
    {
        if ($this->type === self::TYPE_LABEL) {
            return $this->wordMatch && $this->prefixMatch;
        }

        if ($this->type === self::TYPE_GROUP) {
            foreach ($this->alternatives as $alt) {
                if ($alt['wordMatch'] && $alt['prefixMatch']) {
                    return true;
                }
            }
        }

        return false;
    }
}
