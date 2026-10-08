<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            if (app()->environment(['local', 'testing'])) {
                Illuminate\Support\Facades\Route::prefix('mock-api')
                    ->group(base_path('routes/mock.php'));
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => \App\Http\Middleware\EnsureUserHasPermission::class,
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
            'password.changed' => \App\Http\Middleware\EnsurePasswordChanged::class,
            'trusted.origin' => \App\Http\Middleware\EnsureTrustedOrigin::class,
        ]);

        // Contabo / SSI Nginx terminates TLS and forwards X-Forwarded-*.
        // Prefer TRUSTED_PROXIES=comma-separated IPs; "*" only when the API is never exposed bare.
        $trustedProxies = env('TRUSTED_PROXIES', '*');
        $proxyAt = ($trustedProxies === null || $trustedProxies === '' || $trustedProxies === '*')
            ? '*'
            : array_values(array_filter(array_map('trim', explode(',', (string) $trustedProxies))));
        $middleware->trustProxies(
            at: $proxyAt,
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX,
        );

        // API-only app: never redirect guests to a named web `login` route (causes 500).
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            return '/';
        });

        // Auth cookie payload is encrypted via Crypt in AuthCookie (not double-encrypted by framework).
        $middleware->encryptCookies(except: [
            \App\Support\AuthCookie::NAME,
        ]);

        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\EnsureTrustedOrigin::class,
            \App\Http\Middleware\AttachBearerFromCookie::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            if (config('app.debug') || $e instanceof \Illuminate\Validation\ValidationException) {
                return null;
            }

            if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                return null;
            }

            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                return null;
            }

            report($e);

            return response()->json([
                'message' => 'A server error occurred. Please try again later.',
            ], 500);
        });
    })->create();
