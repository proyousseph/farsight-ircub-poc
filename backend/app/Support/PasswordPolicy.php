<?php

namespace App\Support;

use App\Services\SystemConfigService;
use Illuminate\Validation\Rules\Password;

class PasswordPolicy
{
    public static function rule(): Password
    {
        $svc = app(SystemConfigService::class);
        $min = (int) $svc->get('password_min_length', config('ircub.password_policy.min_length', 10));
        $complex = (bool) $svc->get('password_require_complexity', true);

        $rule = Password::min(max(8, $min));

        if ($complex) {
            $rule = $rule->mixedCase()->numbers()->symbols();
        }

        return $rule;
    }

    public static function description(): string
    {
        $svc = app(SystemConfigService::class);
        $min = (int) $svc->get('password_min_length', 10);
        $complex = (bool) $svc->get('password_require_complexity', true);

        if ($complex) {
            return "Min {$min} chars with upper, lower, number, and symbol (e.g. Password@123).";
        }

        return "Min {$min} characters.";
    }

    /**
     * @return array<string, mixed>
     */
    public static function meta(): array
    {
        $svc = app(SystemConfigService::class);
        $min = (int) $svc->get('password_min_length', 10);
        $complex = (bool) $svc->get('password_require_complexity', true);

        return [
            'min_length' => $min,
            'require_uppercase' => $complex,
            'require_lowercase' => $complex,
            'require_number' => $complex,
            'require_symbol' => $complex,
            'description' => self::description(),
        ];
    }
}
