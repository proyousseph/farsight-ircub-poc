<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Role extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_system',
        'is_active',
        'parent_id',
        'level',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'level' => 'integer',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Ancestors from immediate parent up to root.
     *
     * @return Collection<int, Role>
     */
    public function ancestors(): Collection
    {
        $chain = collect();
        $current = $this->parent;
        $guard = 0;
        while ($current && $guard < 20) {
            $chain->push($current);
            $current = $current->parent;
            $guard++;
        }

        return $chain;
    }

    /**
     * Permission slugs allowed for this role when a parent is set:
     * must be a subset of the parent's direct permissions (hierarchical constraint).
     *
     * @return list<string>|null  null = no parent constraint
     */
    public function parentPermissionSlugs(): ?array
    {
        if (! $this->parent_id) {
            return null;
        }

        $parent = $this->parent()->with('permissions')->first();
        if (! $parent) {
            return null;
        }

        return $parent->permissions->pluck('slug')->values()->all();
    }
}
