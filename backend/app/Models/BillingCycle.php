<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingCycle extends Model
{
    protected $fillable = [
        'period',
        'status',
        'started_at',
        'completed_at',
        'accounts_processed',
        'bills_generated',
        'bills_held',
        'exceptions_count',
        'exception_report',
        'summary',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'exception_report' => 'array',
            'summary' => 'array',
        ];
    }

    public function bills(): HasMany
    {
        return $this->hasMany(WaterBill::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
