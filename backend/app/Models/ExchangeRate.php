<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    protected $fillable = [
        'base_currency',
        'quote_currency',
        'rate',
        'source',
        'retrieved_at',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:6',
            'retrieved_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }
}
