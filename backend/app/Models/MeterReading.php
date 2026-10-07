<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeterReading extends Model
{
    protected $fillable = [
        'water_account_id',
        'reading_date',
        'reading_value',
        'previous_reading',
        'consumption',
        'is_rollover',
        'is_meter_replacement',
        'status',
        'period',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'reading_date' => 'date',
            'reading_value' => 'decimal:3',
            'previous_reading' => 'decimal:3',
            'consumption' => 'decimal:3',
            'is_rollover' => 'boolean',
            'is_meter_replacement' => 'boolean',
        ];
    }

    public function waterAccount(): BelongsTo
    {
        return $this->belongsTo(WaterAccount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
