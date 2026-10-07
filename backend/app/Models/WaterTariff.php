<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaterTariff extends Model
{
    protected $fillable = [
        'tariff_class',
        'name',
        'tiers',
        'fixed_charge',
        'is_active',
        'effective_from',
    ];

    protected function casts(): array
    {
        return [
            'tiers' => 'array',
            'fixed_charge' => 'decimal:2',
            'is_active' => 'boolean',
            'effective_from' => 'date',
        ];
    }

    public static function activeFor(string $tariffClass, ?string $asOf = null): ?self
    {
        $date = $asOf ? date('Y-m-d', strtotime($asOf)) : now()->toDateString();

        return static::query()
            ->where('tariff_class', $tariffClass)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $date)
            ->orderByDesc('effective_from')
            ->first();
    }
}
