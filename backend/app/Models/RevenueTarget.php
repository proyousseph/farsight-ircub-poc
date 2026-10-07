<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevenueTarget extends Model
{
    protected $fillable = [
        'period_type',
        'period_key',
        'revenue_code',
        'target_amount',
        'currency',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'target_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
