<?php

namespace App\Support;

use App\Models\User;

class DemoAccounts
{
    public static function skipPasswordChange(): bool
    {
        return (bool) config('ircub.demo_skip_password_change', false);
    }

    public static function settleEnabled(): bool
    {
        return (bool) config('ircub.demo_settle', false);
    }

    public static function isProtectedEmail(?string $email): bool
    {
        if (! $email) {
            return false;
        }

        return str_ends_with(strtolower($email), '@ircub.test');
    }

    public static function isProtectedUser(?User $user): bool
    {
        return $user !== null && self::isProtectedEmail($user->email);
    }

    /**
     * When the hosted-demo lock flag is on, block voluntary password changes
     * and admin mutations that would lock assessors out of @ircub.test accounts.
     */
    public static function assertMutable(?User $target): void
    {
        if (! self::skipPasswordChange() || ! self::isProtectedUser($target)) {
            return;
        }

        abort(403, 'Hosted demo accounts (@ircub.test) are locked while IRCUB_DEMO_SKIP_PASSWORD_CHANGE is enabled.');
    }
}
