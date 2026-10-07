<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DashboardDailyAggregate extends Model
{
    protected $fillable = [
        'stat_date',
        'revenue_code',
        'channel',
        'payment_count',
        'collected_amount',
        'reversal_count',
        'reversed_amount',
    ];

    protected function casts(): array
    {
        return [
            'stat_date' => 'date',
            'collected_amount' => 'decimal:2',
            'reversed_amount' => 'decimal:2',
        ];
    }
}
