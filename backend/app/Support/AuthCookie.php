<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Cookie;

class AuthCookie
{
    public const NAME = 'ircub_token';

    public static function make(string $token): Cookie
    {
        $minutes = (int) config('sanctum.expiration', 480);
        if ($minutes <= 0) {
            $minutes = 480;
        }

        $secure = (bool) config('session.secure', false)
            || (! app()->environment(['local', 'testing']) && request()->secure());

        $sameSite = strtolower((string) config('ircub.auth_cookie.same_site', 'lax'));
        if (! in_array($sameSite, ['lax', 'strict', 'none'], true)) {
            $sameSite = 'lax';
        }
        if ($sameSite === 'none') {
            $secure = true;
        }

        return cookie(
            self::NAME,
            self::encode($token),
            $minutes,
            '/',
            config('session.domain'),
            $secure,
            true,
            false,
            $sameSite
        );
    }

    public static function forget(): Cookie
    {
        return cookie()->forget(self::NAME);
    }

    public static function tokenFrom(Request $request): ?string
    {
        $raw = $request->cookie(self::NAME);
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        return self::decode($raw);
    }

    public static function encode(string $token): string
    {
        return Crypt::encryptString($token);
    }

    public static function decode(string $encoded): ?string
    {
        try {
            $token = Crypt::decryptString($encoded);
        } catch (\Throwable) {
            return null;
        }

        if (! is_string($token) || $token === '' || ! str_contains($token, '|')) {
            return null;
        }

        return $token;
    }
}
