<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FmisJournalBatch extends Model
{
    protected $fillable = [
        'batch_number',
        'journal_date',
        'status',
        'fmis_reference',
        'line_count',
        'total_amount',
        'failure_reason',
        'request_payload',
        'response_payload',
        'posted_at',
        'reversed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'journal_date' => 'date',
            'total_amount' => 'decimal:2',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'posted_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FmisJournalLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
