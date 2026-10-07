<?php

namespace App\Support;

use Illuminate\Http\Request;

class Pagination
{
    public static function perPage(Request $request, int $default = 15, int $max = 100): int
    {
        $value = (int) $request->integer('per_page', $default);

        return max(1, min($max, $value > 0 ? $value : $default));
    }
}
