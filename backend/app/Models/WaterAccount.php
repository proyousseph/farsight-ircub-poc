<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
