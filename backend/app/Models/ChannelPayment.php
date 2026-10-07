<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelPayment extends Model
{
    protected $fillable = [
        'payer_id',
        'assessment_id',
        'water_bill_id',
        'payment_id',
        'revenue_code',
        'channel',
        'amount_usd',
        'amount_local',
        'local_currency',
        'fx_rate',
        'exchange_rate_id',
        'external_ref',
        'provider_txn_id',
        'status',
        'retry_count',
        'max_retries',
        'last_status_check_at',
        'next_retry_at',
        'callback_verified',
        'initiate_payload',
        'callback_payload',
        'status_history',
        'supervisor_notified_at',
        'failure_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount_usd' => 'decimal:2',
            'amount_local' => 'decimal:2',
            'fx_rate' => 'decimal:6',
            'callback_verified' => 'boolean',
            'initiate_payload' => 'array',
            'callback_payload' => 'array',
            'status_history' => 'array',
            'last_status_check_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'supervisor_notified_at' => 'datetime',
        ];
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function waterBill(): BelongsTo
    {
        return $this->belongsTo(WaterBill::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function exchangeRate(): BelongsTo
    {
        return $this->belongsTo(ExchangeRate::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pushHistory(string $event, array $data = []): void
    {
        $history = $this->status_history ?? [];
        $history[] = array_merge([
            'event' => $event,
            'status' => $this->status,
            'at' => now()->toIso8601String(),
        ], $data);
        $this->status_history = $history;
    }
}
