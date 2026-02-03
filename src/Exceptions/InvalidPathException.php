<?php

declare(strict_types=1);

namespace Birdcar\LabelTree\Exceptions;

use InvalidArgumentException;

class InvalidPathException extends InvalidArgumentException
{
    public static function consecutiveDots(string $path): self
    {
        return new self("Invalid path: consecutive dots in '{$path}'");
    }

    public static function invalidBoundary(string $path): self
    {
        return new self("Invalid path: cannot start or end with dot in '{$path}'");
    }

    public static function invalidLabel(string $label, string $path): self
    {
        return new self("Invalid label '{$label}' in path '{$path}'. Labels must be alphanumeric with underscores and hyphens.");
    }
}
