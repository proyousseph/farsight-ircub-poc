<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

class PasswordPolicy
{
    public static function rule(): Password
    {
        $cfg = config('ircub.password_policy');

        $rule = Password::min((int) ($cfg['min_length'] ?? 10));

        if ($cfg['require_uppercase'] ?? true) {
            $rule = $rule->mixedCase();
        }
        if ($cfg['require_number'] ?? true) {
            $rule = $rule->numbers();
        }
        if ($cfg['require_symbol'] ?? true) {
            $rule = $rule->symbols();
        }

        return $rule;
    }

    public static function description(): string
    {
        return (string) config('ircub.password_policy.description');
    }

    /**
     * @return array<string, mixed>
     */
    public static function meta(): array
    {
        return [
            'min_length' => (int) config('ircub.password_policy.min_length', 10),
            'require_uppercase' => (bool) config('ircub.password_policy.require_uppercase', true),
            'require_lowercase' => (bool) config('ircub.password_policy.require_lowercase', true),
            'require_number' => (bool) config('ircub.password_policy.require_number', true),
            'require_symbol' => (bool) config('ircub.password_policy.require_symbol', true),
            'description' => self::description(),
        ];
    }
}
