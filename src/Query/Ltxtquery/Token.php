<?php

declare(strict_types=1);

namespace Birdcar\LabelGraph\Query\Ltxtquery;

final class Token
{
    public const TYPE_WORD = 'word';

    public const TYPE_AND = 'and';

    public const TYPE_OR = 'or';

    public const TYPE_NOT = 'not';

    public const TYPE_GROUP = 'group';

    /**
     * @param  array<int, Token>  $children  For AND/OR/NOT/GROUP nodes
     */
    public function __construct(
        public readonly string $type,
        public readonly ?string $value = null,
        public readonly bool $caseInsensitive = false,
        public readonly bool $prefixMatch = false,
        public readonly bool $wordMatch = false,
        public readonly array $children = [],
    ) {}

    public static function word(
        string $value,
        bool $caseInsensitive = false,
        bool $prefixMatch = false,
        bool $wordMatch = false,
    ): self {
        return new self(
            type: self::TYPE_WORD,
            value: $value,
            caseInsensitive: $caseInsensitive,
            prefixMatch: $prefixMatch,
            wordMatch: $wordMatch,
        );
    }

    public static function and(Token $left, Token $right): self
    {
        return new self(type: self::TYPE_AND, children: [$left, $right]);
    }

    public static function or(Token $left, Token $right): self
    {
        return new self(type: self::TYPE_OR, children: [$left, $right]);
    }

    public static function not(Token $operand): self
    {
        return new self(type: self::TYPE_NOT, children: [$operand]);
    }

    public static function group(Token $inner): self
    {
        return new self(type: self::TYPE_GROUP, children: [$inner]);
    }
}
