<?php

namespace App\Services;

use App\Models\Assessment;
use Illuminate\Support\Str;

class ControlNumberGenerator
{
    public static function next(string $revenueCode): string
    {
        do {
            $number = strtoupper($revenueCode).'-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        } while (Assessment::query()->where('control_number', $number)->exists());

        return $number;
    }
}
