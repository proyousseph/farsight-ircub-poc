<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class SystemConfigService
{
    public const CACHE_KEY = 'ircub.system_settings';

    /**
     * Default POC-tunable settings (overridable in DB).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function defaults(): array
    {
        return [
            'org_name' => [
                'label' => 'Organisation name',
                'group' => 'general',
                'type' => 'string',
                'description' => 'Shown on bills and admin screens.',
                'value' => 'IRCUB — Ministry of Finance (POC)',
            ],
            'password_min_length' => [
                'label' => 'Password minimum length',
                'group' => 'security',
                'type' => 'int',
                'description' => 'Minimum characters for new/updated passwords.',
                'value' => (int) config('ircub.password_policy.min_length', 10),
            ],
            'password_require_complexity' => [
                'label' => 'Require password complexity',
                'group' => 'security',
                'type' => 'bool',
                'description' => 'Require upper/lower/number/symbol when enabled.',
                'value' => true,
            ],
            'two_factor_globally_enabled' => [
                'label' => 'Allow optional 2FA stub',
                'group' => 'security',
                'type' => 'bool',
                'description' => 'When off, per-user 2FA flags are ignored at login.',
                'value' => (bool) config('ircub.two_factor.enabled_globally', true),
            ],
            'abnormal_consumption_pct' => [
                'label' => 'Abnormal consumption threshold (%)',
                'group' => 'water',
                'type' => 'int',
                'description' => 'Hold bills when consumption exceeds this % of the 3-month average (default 200).',
                'value' => 200,
            ],
            'channel_max_retries' => [
                'label' => 'Channel payment max retries',
                'group' => 'channels',
                'type' => 'int',
                'description' => 'Failed channel status checks before permanent failure.',
                'value' => (int) config('channels.max_retries', 3),
            ],
            'channel_retry_delay_seconds' => [
                'label' => 'Channel retry delay (seconds)',
                'group' => 'channels',
                'type' => 'int',
                'description' => 'Delay between automatic channel status retries.',
                'value' => (int) config('channels.retry_delay_seconds', 30),
            ],
        ];
    }

    public function ensureSeeded(): void
    {
        foreach (self::defaults() as $key => $meta) {
            SystemSetting::query()->firstOrCreate(
                ['key' => $key],
                [
                    'label' => $meta['label'],
                    'group' => $meta['group'],
                    'type' => $meta['type'],
                    'description' => $meta['description'],
                    'value' => ['v' => $meta['value']],
                ]
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, 60, function () {
            $this->ensureSeeded();

            return SystemSetting::query()
                ->orderBy('group')
                ->orderBy('key')
                ->get()
                ->mapWithKeys(fn (SystemSetting $s) => [$s->key => $s->decodedValue()])
                ->all();
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    /**
     * @param  array<string, mixed>  $updates
     * @return array<string, mixed>
     */
    public function updateMany(array $updates): array
    {
        $this->ensureSeeded();
        $allowed = array_keys(self::defaults());

        foreach ($updates as $key => $value) {
            if (! in_array($key, $allowed, true)) {
                continue;
            }

            $meta = self::defaults()[$key];
            $typed = match ($meta['type']) {
                'int' => (int) $value,
                'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                default => is_string($value) ? $value : (string) $value,
            };

            SystemSetting::query()->where('key', $key)->update([
                'value' => ['v' => $typed],
            ]);
        }

        Cache::forget(self::CACHE_KEY);

        return $this->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function catalog(): array
    {
        $this->ensureSeeded();
        $values = $this->all();

        return SystemSetting::query()
            ->orderBy('group')
            ->orderBy('key')
            ->get()
            ->map(fn (SystemSetting $s) => [
                'key' => $s->key,
                'label' => $s->label,
                'group' => $s->group,
                'type' => $s->type,
                'description' => $s->description,
                'value' => $values[$s->key] ?? $s->decodedValue(),
            ])
            ->values()
            ->all();
    }
}
