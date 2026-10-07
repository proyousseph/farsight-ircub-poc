<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mitigate CSRF for cookie-authenticated browser requests.
 * Non-browser clients (no Origin/Referer) are allowed (API tools / tests).
 */
class EnsureTrustedOrigin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array(strtoupper($request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        // Public HMAC callback is not cookie-authenticated.
        if ($request->is('api/channel/callback')) {
            return $next($request);
        }

        $origin = $request->headers->get('Origin');
        $referer = $request->headers->get('Referer');

        if (! $origin && ! $referer) {
            return $next($request);
        }

        $candidate = $origin ?: $referer;
        $host = parse_url($candidate, PHP_URL_SCHEME).'://'.parse_url($candidate, PHP_URL_HOST);
        $port = parse_url($candidate, PHP_URL_PORT);
        if ($port) {
            $host .= ':'.$port;
        }

        $allowed = array_values(array_filter(config('cors.allowed_origins', [])));
        $allowed[] = rtrim((string) config('app.url'), '/');

        foreach ($allowed as $allowedOrigin) {
            if (strcasecmp(rtrim((string) $allowedOrigin, '/'), rtrim($host, '/')) === 0) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => 'Untrusted request origin.',
        ], 403);
    }
}
