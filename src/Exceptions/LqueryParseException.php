<?php

declare(strict_types=1);

namespace Birdcar\LabelGraph\Exceptions;

use InvalidArgumentException;

class LqueryParseException extends InvalidArgumentException
{
    public static function emptyPattern(): self
    {
        return new self('Empty lquery pattern');
    }

    public static function emptyElement(): self
    {
        return new self('Empty element in lquery pattern');
    }

    public static function invalidQuantifier(string $quantifier): self
    {
        return new self("Invalid quantifier: {$quantifier}");
    }

    public static function invalidLabel(string $label): self
    {
        return new self("Invalid label characters: {$label}");
    }
}
