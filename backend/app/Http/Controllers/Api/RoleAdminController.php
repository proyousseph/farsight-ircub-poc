<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleAdminController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::query()
            ->with('permissions:id,name,slug,module')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $roles]);
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
            'permission_ids' => ['required', 'array', 'min:1'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        $slug = $data['slug'] ?? Str::slug($data['name']);

        $role = Role::query()->create([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'is_system' => false,
            'is_active' => true,
        ]);
        $role->permissions()->sync($data['permission_ids']);

        return response()->json([
            'message' => 'Custom role created.',
            'role' => $role->load('permissions:id,name,slug,module'),
        ], 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'permission_ids' => ['sometimes', 'array', 'min:1'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
            'slug' => ['sometimes', 'string', 'max:120', Rule::unique('roles', 'slug')->ignore($role->id)],
        ]);

        if ($role->is_system && isset($data['slug']) && $data['slug'] !== $role->slug) {
            return response()->json(['message' => 'System role slug cannot be changed.'], 422);
        }

        $role->fill(collect($data)->only(['name', 'description', 'is_active', 'slug'])->all());
        $role->save();

        if (isset($data['permission_ids'])) {
            $role->permissions()->sync($data['permission_ids']);
        }

        return response()->json([
            'message' => 'Role updated.',
            'role' => $role->fresh()->load('permissions:id,name,slug,module'),
        ]);
    }
}
