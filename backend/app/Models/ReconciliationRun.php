<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReconciliationRun extends Model
{
    protected $fillable = [
        'report_date',
        'channel',
        'status',
        'ircub_total',
        'channel_total',
        'difference',
        'matched_count',
        'ircub_only_count',
        'channel_only_count',
        'statement_path',
        'summary',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'ircub_total' => 'decimal:2',
            'channel_total' => 'decimal:2',
            'difference' => 'decimal:2',
            'summary' => 'array',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReconciliationItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
