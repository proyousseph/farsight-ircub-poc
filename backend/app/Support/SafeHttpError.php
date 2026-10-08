<?php

namespace App\Support;

/**
 * Avoid leaking internal exception detail when APP_DEBUG is false.
 * Domain InvalidArgumentException messages are treated as client-safe.
 */
class SafeHttpError
{
    public static function message(\Throwable $e, string $fallback): string
    {
        if (config('app.debug')) {
            return $e->getMessage();
        }

        if ($e instanceof \InvalidArgumentException) {
            return $e->getMessage();
        }

        return $fallback;
    }
}
