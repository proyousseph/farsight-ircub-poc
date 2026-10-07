<?php

namespace App\Support;

use Illuminate\Http\Request;
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

        // Cross-site SPA (e.g. different host) needs SameSite=None + Secure.
        // Same-host local (localhost:5173 → localhost:8001) can use Lax over HTTP.
        $sameSite = $secure ? 'none' : 'lax';

        return cookie(
            self::NAME,
            // Sanctum tokens contain "|" which is unsafe in raw cookie values.
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
        return rtrim(strtr(base64_encode($token), '+/', '-_'), '=');
    }

    public static function decode(string $encoded): ?string
    {
        $padded = strtr($encoded, '-_', '+/');
        $pad = strlen($padded) % 4;
        if ($pad > 0) {
            $padded .= str_repeat('=', 4 - $pad);
        }

        $token = base64_decode($padded, true);
        if (! is_string($token) || $token === '' || ! str_contains($token, '|')) {
            return null;
        }

        return $token;
    }
}
