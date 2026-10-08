<?php

namespace App\Models;

use App\Support\SensitivePayload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
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
        'prev_hash',
        'entry_hash',
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
        $before = SensitivePayload::redact($before);
        $after = SensitivePayload::redact($after);
        $ip = Request::ip();

        return DB::transaction(function () use ($entityType, $entityId, $action, $before, $after, $userId, $ip) {
            // Serialize writers so prev_hash / entry_hash form a linear chain.
            $prev = static::query()->orderByDesc('id')->lockForUpdate()->first();
            $prevHash = $prev?->entry_hash ?: str_repeat('0', 64);

            $canonical = json_encode([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'action' => $action,
                'user_id' => $userId,
                'before_values' => $before,
                'after_values' => $after,
                'ip_address' => $ip,
                'prev_hash' => $prevHash,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $entryHash = hash('sha256', (string) $canonical);

            return static::query()->create([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'action' => $action,
                'user_id' => $userId,
                'before_values' => $before,
                'after_values' => $after,
                'ip_address' => $ip,
                'prev_hash' => $prevHash,
                'entry_hash' => $entryHash,
            ]);
        });
    }

    /**
     * Verify the hash chain from the first row through $throughId (or all rows).
     *
     * @return array{ok: bool, checked: int, broken_at: int|null}
     */
    public static function verifyChain(?int $throughId = null): array
    {
        $query = static::query()->orderBy('id');
        if ($throughId) {
            $query->where('id', '<=', $throughId);
        }

        $prevHash = str_repeat('0', 64);
        $checked = 0;

        foreach ($query->cursor() as $row) {
            $checked++;
            if (($row->prev_hash ?: '') !== $prevHash) {
                return ['ok' => false, 'checked' => $checked, 'broken_at' => (int) $row->id];
            }

            $canonical = json_encode([
                'entity_type' => $row->entity_type,
                'entity_id' => (int) $row->entity_id,
                'action' => $row->action,
                'user_id' => $row->user_id,
                'before_values' => $row->before_values,
                'after_values' => $row->after_values,
                'ip_address' => $row->ip_address,
                'prev_hash' => $row->prev_hash,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $expected = hash('sha256', (string) $canonical);
            if (($row->entry_hash ?: '') !== $expected) {
                return ['ok' => false, 'checked' => $checked, 'broken_at' => (int) $row->id];
            }

            $prevHash = $row->entry_hash;
        }

        return ['ok' => true, 'checked' => $checked, 'broken_at' => null];
    }
}
