<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoleAdminController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::query()
            ->with(['permissions:id,name,slug,module', 'parent:id,name,slug,level'])
            ->orderBy('level')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $roles,
            'tree' => $this->buildTree($roles),
        ]);
    }

    public function permissions(): JsonResponse
    {
        $permissions = Permission::query()->orderBy('module')->orderBy('name')->get();

        return response()->json(['data' => $permissions]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'unique:roles,slug'],
            'description' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'integer', 'exists:roles,id'],
            'permission_ids' => ['required', 'array', 'min:1'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        $slug = $data['slug'] ?? Str::slug($data['name']);
        $parent = isset($data['parent_id']) ? Role::query()->find($data['parent_id']) : null;
        $level = $parent ? ((int) $parent->level + 1) : 0;

        $this->assertSubsetOfParent($parent, $data['permission_ids']);

        $role = Role::query()->create([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'is_system' => false,
            'is_active' => true,
            'parent_id' => $parent?->id,
            'level' => $level,
        ]);
        $role->permissions()->sync($data['permission_ids']);

        return response()->json([
            'message' => 'Custom role created.',
            'role' => $role->load(['permissions:id,name,slug,module', 'parent:id,name,slug,level']),
        ], 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'parent_id' => ['nullable', 'integer', 'exists:roles,id'],
            'permission_ids' => ['sometimes', 'array', 'min:1'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
            'slug' => ['sometimes', 'string', 'max:120', Rule::unique('roles', 'slug')->ignore($role->id)],
        ]);

        if ($role->is_system && isset($data['slug']) && $data['slug'] !== $role->slug) {
            return response()->json(['message' => 'System role slug cannot be changed.'], 422);
        }

        if (array_key_exists('parent_id', $data)) {
            if ($data['parent_id'] === $role->id) {
                throw ValidationException::withMessages(['parent_id' => 'A role cannot be its own parent.']);
            }
            // Prevent cycles: parent cannot be a descendant
            if ($data['parent_id']) {
                $descendantIds = $this->descendantIds($role);
                if (in_array((int) $data['parent_id'], $descendantIds, true)) {
                    throw ValidationException::withMessages(['parent_id' => 'Cannot assign a descendant as parent.']);
                }
            }
            $parent = $data['parent_id'] ? Role::query()->find($data['parent_id']) : null;
            $role->parent_id = $parent?->id;
            $role->level = $parent ? ((int) $parent->level + 1) : 0;
        }

        if (isset($data['permission_ids'])) {
            $parent = $role->parent_id ? Role::query()->find($role->parent_id) : null;
            $this->assertSubsetOfParent($parent, $data['permission_ids']);
            $role->permissions()->sync($data['permission_ids']);
        }

        $role->fill(collect($data)->only(['name', 'description', 'is_active', 'slug'])->all());
        $role->save();

        return response()->json([
            'message' => 'Role updated.',
            'role' => $role->fresh()->load(['permissions:id,name,slug,module', 'parent:id,name,slug,level']),
        ]);
    }

    /**
     * @param  list<int>  $permissionIds
     */
    private function assertSubsetOfParent(?Role $parent, array $permissionIds): void
    {
        if (! $parent) {
            return;
        }

        $allowed = $parent->permissions()->pluck('permissions.id')->map(fn ($id) => (int) $id)->all();
        $extra = array_diff(array_map('intval', $permissionIds), $allowed);
        if ($extra !== []) {
            throw ValidationException::withMessages([
                'permission_ids' => 'Child role permissions must be a subset of the parent role permissions (hierarchy).',
            ]);
        }
    }

    /**
     * @return list<int>
     */
    private function descendantIds(Role $role): array
    {
        $ids = [];
        $queue = [$role->id];
        while ($queue) {
            $id = array_shift($queue);
            $children = Role::query()->where('parent_id', $id)->pluck('id')->all();
            foreach ($children as $childId) {
                $ids[] = (int) $childId;
                $queue[] = $childId;
            }
        }

        return $ids;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Role>  $roles
     * @return list<array<string, mixed>>
     */
    private function buildTree($roles): array
    {
        $byParent = $roles->groupBy(fn (Role $r) => $r->parent_id ?? 0);

        $walk = function ($parentKey) use (&$walk, $byParent): array {
            return ($byParent->get($parentKey) ?? collect())
                ->map(fn (Role $r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'slug' => $r->slug,
                    'level' => $r->level,
                    'is_system' => $r->is_system,
                    'children' => $walk($r->id),
                ])
                ->values()
                ->all();
        };

        return $walk(0);
    }
}
