<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuthCookie;
use App\Support\PasswordPolicy;
use App\Support\Totp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'otp' => ['nullable', 'string', 'max:12'],
        ]);

        /** @var User|null $user */
        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid email or password.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Invalid email or password.'],
            ]);
        }

        $twoFactorGlobal = (bool) app(\App\Services\SystemConfigService::class)
            ->get('two_factor_globally_enabled', config('ircub.two_factor.enabled_globally', false));
        $twoFactorOn = $twoFactorGlobal && $user->two_factor_enabled;

        if ($twoFactorOn) {
            $challenge = $this->challengeTwoFactor($user, $credentials['otp'] ?? null);
            if ($challenge !== null) {
                return $challenge;
            }
        }

        // Single-session style: drop previous web tokens on login.
        $user->tokens()->where('name', 'ircub-web')->delete();
        $token = $user->createToken('ircub-web')->plainTextToken;

        $payload = [
            'message' => 'Login successful.',
            'cookie_auth' => true,
            'token_type' => 'Bearer',
            'user' => $user->toAuthArray(),
            'password_policy' => PasswordPolicy::meta(),
            'two_factor_verified' => $twoFactorOn,
        ];

        // Browser SPA uses HttpOnly cookie only. Token in JSON is opt-in for API clients/tests.
        if ($this->shouldReturnToken($request)) {
            $payload['token'] = $token;
        }

        return response()->json($payload)->withCookie(AuthCookie::make($token));
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'user' => $user->toAuthArray(),
            'password_policy' => PasswordPolicy::meta(),
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', PasswordPolicy::rule()],
        ]);

        \App\Support\DemoAccounts::assertMutable($user);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        if (Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['New password must be different from the current password.'],
            ]);
        }

        $user->password = $data['password'];
        $user->must_change_password = false;
        $user->save();

        // Invalidate all sessions; issue a fresh cookie token.
        $user->tokens()->delete();
        $token = $user->createToken('ircub-web')->plainTextToken;

        $payload = [
            'message' => 'Password updated successfully.',
            'cookie_auth' => true,
            'user' => $user->fresh()->toAuthArray(),
            'password_policy' => PasswordPolicy::meta(),
        ];
        if ($this->shouldReturnToken($request)) {
            $payload['token'] = $token;
            $payload['token_type'] = 'Bearer';
        }

        return response()->json($payload)->withCookie(AuthCookie::make($token));
    }

    public function setupTwoFactor(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Re-setup while 2FA is already active must prove password (+ OTP if confirmed).
        if ($user->two_factor_enabled || $user->two_factor_confirmed_at) {
            $data = $request->validate([
                'password' => ['required', 'string'],
                'otp' => ['nullable', 'string', 'max:12'],
            ]);
            if (! Hash::check($data['password'], $user->password)) {
                throw ValidationException::withMessages([
                    'password' => ['Password is incorrect.'],
                ]);
            }
            if ($user->two_factor_secret && $user->two_factor_confirmed_at) {
                if (empty($data['otp']) || ! Totp::verify($user->two_factor_secret, $data['otp'])) {
                    throw ValidationException::withMessages([
                        'otp' => ['Invalid authenticator code.'],
                    ]);
                }
            }
        }

        $secret = Totp::generateSecret();
        $user->two_factor_secret = $secret;
        $user->two_factor_confirmed_at = null;
        $user->two_factor_enabled = false;
        $user->save();

        return response()->json([
            'message' => 'Scan this secret in your authenticator app, then confirm with a code.',
            'secret' => $secret,
            'otpauth_url' => Totp::otpAuthUri($secret, $user->email, config('app.name', 'IRCUB')),
        ]);
    }

    public function confirmTwoFactor(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'otp' => ['required', 'string', 'max:12'],
        ]);

        if (! $user->two_factor_secret || ! Totp::verify($user->two_factor_secret, $data['otp'])) {
            throw ValidationException::withMessages([
                'otp' => ['Invalid authenticator code.'],
            ]);
        }

        $user->two_factor_enabled = true;
        $user->two_factor_confirmed_at = now();
        $user->save();

        return response()->json([
            'message' => 'Two-factor authentication enabled.',
            'user' => $user->fresh()->toAuthArray(),
        ]);
    }

    public function disableTwoFactor(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'password' => ['required', 'string'],
            'otp' => ['nullable', 'string', 'max:12'],
        ]);

        if (! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Password is incorrect.'],
            ]);
        }

        if ($user->two_factor_secret && $user->two_factor_confirmed_at) {
            if (empty($data['otp']) || ! Totp::verify($user->two_factor_secret, $data['otp'])) {
                throw ValidationException::withMessages([
                    'otp' => ['Invalid authenticator code.'],
                ]);
            }
        }

        $user->two_factor_enabled = false;
        $user->two_factor_secret = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        return response()->json([
            'message' => 'Two-factor authentication disabled.',
            'user' => $user->fresh()->toAuthArray(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ])->withCookie(AuthCookie::forget());
    }

    private function shouldReturnToken(Request $request): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        // Header-only opt-in for API clients — do not accept return_token in body/query.
        return $request->header('X-IRCUB-Return-Token') === '1';
    }

    private function challengeTwoFactor(User $user, ?string $otp): ?JsonResponse
    {
        $hasTotp = filled($user->two_factor_secret) && filled($user->two_factor_confirmed_at);
        $stubAllowed = (bool) config('ircub.two_factor.allow_stub', false);
        $stubExpected = (string) (config('ircub.two_factor.demo_otp') ?? '');

        if (! $otp) {
            return response()->json([
                'message' => 'Two-factor authentication required.',
                'requires_2fa' => true,
                'two_factor' => [
                    'method' => $hasTotp ? 'totp' : ($stubAllowed ? 'otp_stub' : 'totp'),
                    'hint' => $hasTotp
                        ? 'Enter the code from your authenticator app.'
                        : ($stubAllowed
                            ? 'Enter the local demo OTP, or complete TOTP setup.'
                            : 'Complete TOTP setup before signing in.'),
                ],
                'password_policy' => PasswordPolicy::meta(),
            ], 401);
        }

        if ($hasTotp) {
            if (! Totp::verify($user->two_factor_secret, $otp)) {
                throw ValidationException::withMessages([
                    'otp' => ['Invalid two-factor code.'],
                ]);
            }

            return null;
        }

        if ($stubAllowed && $stubExpected !== '' && hash_equals($stubExpected, $otp)) {
            return null;
        }

        throw ValidationException::withMessages([
            'otp' => $stubAllowed
                ? ['Invalid two-factor code.']
                : ['Two-factor authentication is required. Complete TOTP setup first.'],
        ]);
    }
}
