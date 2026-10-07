<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaterBill extends Model
{
    protected $fillable = [
        'billing_cycle_id',
        'water_account_id',
        'payer_id',
        'meter_reading_id',
        'bill_number',
        'period',
        'previous_reading',
        'current_reading',
        'consumption',
        'tariff_amount',
        'fixed_charge',
        'arrears_brought_forward',
        'payments_applied',
        'total_due',
        'amount_paid',
        'due_date',
        'status',
        'abnormal_flag',
        'abnormal_reason',
        'pdf_path',
        'notification_status',
        'notification_log',
        'released_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'previous_reading' => 'decimal:3',
            'current_reading' => 'decimal:3',
            'consumption' => 'decimal:3',
            'tariff_amount' => 'decimal:2',
            'fixed_charge' => 'decimal:2',
            'arrears_brought_forward' => 'decimal:2',
            'payments_applied' => 'decimal:2',
            'total_due' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'due_date' => 'date',
            'abnormal_flag' => 'boolean',
            'notification_log' => 'array',
            'released_at' => 'datetime',
        ];
    }

    public function billingCycle(): BelongsTo
    {
        return $this->belongsTo(BillingCycle::class);
    }

    public function waterAccount(): BelongsTo
    {
        return $this->belongsTo(WaterAccount::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class);
    }

    public function meterReading(): BelongsTo
    {
        return $this->belongsTo(MeterReading::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function outstandingAmount(): float
    {
        return max(0, (float) $this->total_due - (float) $this->amount_paid);
    }

    public function refreshStatus(): void
    {
        if ($this->status === 'HELD') {
            return;
        }

        $outstanding = $this->outstandingAmount();

        if ($outstanding <= 0.00001) {
            $this->status = 'PAID';
        } elseif ((float) $this->amount_paid > 0) {
            $this->status = 'PART_PAID';
        } else {
            $this->status = 'RELEASED';
        }

        $this->save();
    }
}
