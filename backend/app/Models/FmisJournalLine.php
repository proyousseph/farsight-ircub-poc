<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FmisJournalLine extends Model
{
    protected $fillable = [
        'fmis_journal_batch_id',
        'payment_id',
        'source_payment_id',
        'revenue_code',
        'gl_code',
        'amount',
        'currency',
        'description',
        'fmis_line_ref',
        'reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'reversed_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(FmisJournalBatch::class, 'fmis_journal_batch_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
