<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'label',
        'group',
        'type',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public function decodedValue(): mixed
    {
        $raw = $this->value;

        // Stored as JSON wrapper: {"v": ...}
        if (is_array($raw) && array_key_exists('v', $raw)) {
            return $raw['v'];
        }

        return $raw;
    }
}
