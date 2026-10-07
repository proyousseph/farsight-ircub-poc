<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PasswordPolicy;
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
                'email' => ['This account has been deactivated.'],
            ]);
        }

        $twoFactorGlobal = (bool) app(\App\Services\SystemConfigService::class)
            ->get('two_factor_globally_enabled', config('ircub.two_factor.enabled_globally', true));
        $twoFactorOn = $twoFactorGlobal && $user->two_factor_enabled;
        if ($twoFactorOn) {
            $otp = $credentials['otp'] ?? null;
            if (! $otp) {
                return response()->json([
                    'message' => 'Two-factor authentication required.',
                    'requires_2fa' => true,
                    'two_factor' => [
                        'method' => 'otp_stub',
                        'hint' => 'Enter the demo OTP configured for this POC.',
                    ],
                    'password_policy' => PasswordPolicy::meta(),
                ], 401);
            }

            $expected = (string) config('ircub.two_factor.demo_otp', '123456');
            if (! hash_equals($expected, $otp)) {
                throw ValidationException::withMessages([
                    'otp' => ['Invalid two-factor code.'],
                ]);
            }
        }

        $token = $user->createToken('ircub-web')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->toAuthArray(),
            'password_policy' => PasswordPolicy::meta(),
            'two_factor_verified' => $twoFactorOn,
        ]);
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

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
