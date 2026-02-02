<?php

declare(strict_types=1);

namespace Birdcar\LabelTree\Exceptions;

use InvalidArgumentException;

class LtxtqueryParseException extends InvalidArgumentException
{
    public static function emptyPattern(): self
    {
        return new self('Empty ltxtquery pattern');
    }

    public static function unexpectedChar(string $char, int $position): self
    {
        return new self("Unexpected character '{$char}' at position {$position}");
    }

    public static function missingCloseParen(int $position): self
    {
        return new self("Missing closing parenthesis at position {$position}");
    }

    public static function expectedWord(int $position): self
    {
        return new self("Expected word at position {$position}");
    }
}
