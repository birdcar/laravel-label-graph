<?php

declare(strict_types=1);

namespace Birdcar\LabelTree\Support;

final class Deprecation
{
    private static bool $enabled = true;

    public static function disable(): void
    {
        self::$enabled = false;
    }

    public static function enable(): void
    {
        self::$enabled = true;
    }

    /**
     * Emit deprecation notice for renamed method.
     */
    public static function methodRenamed(string $class, string $old, string $new): void
    {
        if (self::$enabled) {
            @trigger_error(
                sprintf(
                    '%s::%s() is deprecated, use %s::%s() instead.',
                    $class, $old, $class, $new
                ),
                E_USER_DEPRECATED
            );
        }
    }
}
