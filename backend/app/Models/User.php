<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'is_active',
        'must_change_password',
        'two_factor_enabled',
        'two_factor_confirmed_at',
        'payer_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'two_factor_enabled' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function permissions(): array
    {
        return $this->roles()
            ->where('roles.is_active', true)
            ->with('permissions')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->pluck('slug')
            ->unique()
            ->values()
            ->all();
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles()->where('roles.slug', $slug)->where('roles.is_active', true)->exists();
    }

    public function hasPermission(string $slug): bool
    {
        return in_array($slug, $this->permissions(), true);
    }

    public function toAuthArray(): array
    {
        $roles = $this->roles()->where('roles.is_active', true)->with('permissions')->get();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            'must_change_password' => (bool) $this->must_change_password,
            'two_factor_enabled' => (bool) $this->two_factor_enabled,
            'two_factor_confirmed' => filled($this->two_factor_confirmed_at),
            'payer_id' => $this->payer_id,
            'roles' => $roles->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
            ])->values(),
            'permissions' => $roles
                ->pluck('permissions')
                ->flatten()
                ->pluck('slug')
                ->unique()
                ->values(),
            'features' => [
                'demo_settle' => \App\Support\DemoAccounts::settleEnabled(),
                'demo_accounts_locked' => \App\Support\DemoAccounts::skipPasswordChange(),
            ],
        ];
    }
}
