<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupervisorNotification extends Model
{
    protected $fillable = [
        'type',
        'severity',
        'severity_id',
        'title',
        'message',
        'payload',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'read_at' => 'datetime',
        ];
    }
}
