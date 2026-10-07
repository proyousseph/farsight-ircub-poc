<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GlMapping extends Model
{
    protected $fillable = [
        'revenue_code',
        'gl_code',
        'gl_name',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public static function resolveGlCode(string $revenueCode): ?string
    {
        $mapped = static::query()
            ->where('revenue_code', strtoupper($revenueCode))
            ->where('is_active', true)
            ->value('gl_code');

        if ($mapped) {
            return $mapped;
        }

        return RevenueType::query()
            ->where('revenue_code', strtoupper($revenueCode))
            ->where('is_active', true)
            ->value('gl_code');
    }
}
