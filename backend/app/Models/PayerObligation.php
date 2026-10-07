<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayerObligation extends Model
{
    protected $fillable = [
        'payer_id',
        'revenue_code',
        'name',
        'category',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class);
    }
}
