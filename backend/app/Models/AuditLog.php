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
            // Serialize concurrent writers (API + queue + scheduler). lockForUpdate alone
            // does not serialize inserts under READ COMMITTED when rows don't overlap.
            $driver = DB::connection()->getDriverName();
            if ($driver === 'pgsql') {
                DB::statement('SELECT pg_advisory_xact_lock(?)', [0x49524355]); // 'IRCU'
            } elseif ($driver === 'mysql') {
                DB::select('SELECT GET_LOCK(?, 10) AS l', ['ircub_audit_chain']);
            }
            // SQLite serializes writers at the DB level; PHPUnit is single-process.

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
     * Backfill prev_hash/entry_hash only for rows that still lack a hash.
     * Does NOT rewrite existing hashes (avoids laundering tampering — NEW-B).
     *
     * @return array{backfilled: int}
     */
    public static function backfillChain(): array
    {
        return DB::transaction(function () {
            $driver = DB::connection()->getDriverName();
            if ($driver === 'pgsql') {
                DB::statement('SELECT pg_advisory_xact_lock(?)', [0x49524355]);
            }

            $lastHashed = static::query()
                ->whereNotNull('entry_hash')
                ->where('entry_hash', '!=', '')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            // Leading unhashed rows (before the first hash) are left alone so
            // verifyChain can skip them; filling them would fork a chain that
            // already starts at genesis. Only append hashes after the tip.
            $prevHash = $lastHashed?->entry_hash ?: str_repeat('0', 64);
            $count = 0;

            $query = static::query()
                ->where(function ($q) {
                    $q->whereNull('entry_hash')->orWhere('entry_hash', '');
                })
                ->orderBy('id')
                ->lockForUpdate();

            if ($lastHashed) {
                $query->where('id', '>', $lastHashed->id);
            }

            foreach ($query->cursor() as $row) {
                $canonical = json_encode([
                    'entity_type' => $row->entity_type,
                    'entity_id' => (int) $row->entity_id,
                    'action' => $row->action,
                    'user_id' => $row->user_id,
                    'before_values' => $row->before_values,
                    'after_values' => $row->after_values,
                    'ip_address' => $row->ip_address,
                    'prev_hash' => $prevHash,
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                $entryHash = hash('sha256', (string) $canonical);
                $row->forceFill([
                    'prev_hash' => $prevHash,
                    'entry_hash' => $entryHash,
                ])->save();
                $prevHash = $entryHash;
                $count++;
            }

            return ['backfilled' => $count];
        });
    }

    /**
     * Verify the hash chain. Skips leading pre-hash rows (NULL entry_hash) so
     * Contabo/prod DBs migrated mid-life can verify from the first hashed row.
     *
     * @return array{ok: bool, checked: int, broken_at: int|null, skipped_unhashed: int}
     */
    public static function verifyChain(?int $throughId = null): array
    {
        $query = static::query()->orderBy('id');
        if ($throughId) {
            $query->where('id', '<=', $throughId);
        }

        $prevHash = str_repeat('0', 64);
        $checked = 0;
        $skipped = 0;
        $started = false;

        foreach ($query->cursor() as $row) {
            if (! $started) {
                if (empty($row->entry_hash)) {
                    $skipped++;
                    continue;
                }
                // First hashed row may start after unhashed history — accept its prev_hash as genesis or prior.
                $started = true;
                $prevHash = $row->prev_hash ?: str_repeat('0', 64);
            }

            $checked++;
            if (($row->prev_hash ?: '') !== $prevHash) {
                return ['ok' => false, 'checked' => $checked, 'broken_at' => (int) $row->id, 'skipped_unhashed' => $skipped];
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
                return ['ok' => false, 'checked' => $checked, 'broken_at' => (int) $row->id, 'skipped_unhashed' => $skipped];
            }

            $prevHash = $row->entry_hash;
        }

        return ['ok' => true, 'checked' => $checked, 'broken_at' => null, 'skipped_unhashed' => $skipped];
    }
}
