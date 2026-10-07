<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationItem extends Model
{
    protected $fillable = [
        'reconciliation_run_id',
        'match_status',
        'external_ref',
        'ircub_amount',
        'channel_amount',
        'channel',
        'details',
    ];

    protected function casts(): array
    {
        return [
            'ircub_amount' => 'decimal:2',
            'channel_amount' => 'decimal:2',
            'details' => 'array',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ReconciliationRun::class, 'reconciliation_run_id');
    }
}
