<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payer extends Model
{
    protected $fillable = [
        'payer_type',
        'tin',
        'full_name',
        'national_id',
        'phone',
        'email',
        'address',
        'status',
        'duplicate_flagged',
        'duplicate_reason',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'duplicate_flagged' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function waterAccounts(): HasMany
    {
        return $this->hasMany(WaterAccount::class);
    }

    public function obligations(): HasMany
    {
        return $this->hasMany(PayerObligation::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $builder) use ($like) {
            $builder->where('tin', 'ilike', $like)
                ->orWhere('full_name', 'ilike', $like)
                ->orWhere('phone', 'ilike', $like)
                ->orWhere('email', 'ilike', $like)
                ->orWhere('national_id', 'ilike', $like)
                ->orWhereHas('waterAccounts', function (Builder $accounts) use ($like) {
                    $accounts->where('account_no', 'ilike', $like)
                        ->orWhere('meter_no', 'ilike', $like);
                });
        });
    }

    public function findDuplicateMatches(): array
    {
        $matches = static::query()
            ->where('id', '!=', $this->id ?? 0)
            ->where(function (Builder $query) {
                $query->where('phone', $this->phone);

                if ($this->email) {
                    $query->orWhere('email', $this->email);
                }

                if ($this->national_id) {
                    $query->orWhere('national_id', $this->national_id);
                }
            })
            ->limit(10)
            ->get(['id', 'tin', 'full_name', 'phone', 'email', 'national_id', 'status']);

        return $matches->map(function (self $match) {
            $reasons = [];
            if ($match->phone === $this->phone) {
                $reasons[] = 'phone';
            }
            if ($this->email && $match->email === $this->email) {
                $reasons[] = 'email';
            }
            if ($this->national_id && $match->national_id === $this->national_id) {
                $reasons[] = 'national_id';
            }

            return [
                'id' => $match->id,
                'tin' => $match->tin,
                'full_name' => $match->full_name,
                'matched_on' => $reasons,
            ];
        })->all();
    }
}
