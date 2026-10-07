<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assessment extends Model
{
    protected $fillable = [
        'payer_id',
        'revenue_code',
        'control_number',
        'amount_due',
        'amount_paid',
        'penalty_amount',
        'due_date',
        'status',
        'period',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount_due' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'penalty_amount' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function outstandingAmount(): float
    {
        return max(0, (float) $this->amount_due + (float) $this->penalty_amount - (float) $this->amount_paid);
    }

    public function refreshStatus(): void
    {
        $outstanding = $this->outstandingAmount();

        if ($this->status === 'REVERSED') {
            return;
        }

        if ($outstanding <= 0.00001) {
            $this->status = 'PAID';
        } elseif ((float) $this->amount_paid > 0) {
            $this->status = 'PART_PAID';
        } else {
            $this->status = 'OPEN';
        }

        $this->save();
    }
}
