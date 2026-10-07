<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaterAccount extends Model
{
    protected $fillable = [
        'payer_id',
        'account_no',
        'meter_no',
        'tariff_class',
        'status',
        'location',
    ];

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class);
    }

    public function meterReadings(): HasMany
    {
        return $this->hasMany(MeterReading::class);
    }

    public function waterBills(): HasMany
    {
        return $this->hasMany(WaterBill::class);
    }
}
