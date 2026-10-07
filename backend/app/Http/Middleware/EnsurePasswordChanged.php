<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->must_change_password) {
            return $next($request);
        }

        $path = trim($request->path(), '/');
        $allowed = [
            'api/auth/me',
            'api/auth/logout',
            'api/auth/password',
        ];

        if (in_array($path, $allowed, true)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'You must change your password before continuing.',
            'must_change_password' => true,
        ], 403);
    }
}
