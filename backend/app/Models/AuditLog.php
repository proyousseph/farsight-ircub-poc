<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Request;

class AuditLog extends Model
{
    protected $fillable = [
        'entity_type',
        'entity_id',
        'action',
        'user_id',
        'before_values',
        'after_values',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'before_values' => 'array',
            'after_values' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(
        string $entityType,
        int $entityId,
        string $action,
        ?array $before = null,
        ?array $after = null,
        ?int $userId = null
    ): self {
        return static::query()->create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'user_id' => $userId,
            'before_values' => $before,
            'after_values' => $after,
            'ip_address' => Request::ip(),
        ]);
    }
}
