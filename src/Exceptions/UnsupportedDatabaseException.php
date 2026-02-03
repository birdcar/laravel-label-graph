<?php

declare(strict_types=1);

namespace Birdcar\LabelGraph\Exceptions;

use RuntimeException;

class UnsupportedDatabaseException extends RuntimeException
{
    public static function arrayOperators(string $driver): self
    {
        return new self(
            'Array operators require PostgreSQL with ltree extension. '.
            "Current driver: {$driver}. ".
            'Use supportsArrayOperators() to check availability before calling array methods.'
        );
    }

    public static function gistIndex(string $driver): self
    {
        return new self(
            "GiST indexes require PostgreSQL. Current driver: {$driver}. ".
            'Use standard indexes for other databases.'
        );
    }
}
