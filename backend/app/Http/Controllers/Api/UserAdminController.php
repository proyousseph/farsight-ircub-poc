<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->with('roles:id,name,slug')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($b) use ($term) {
                    $b->where('name', 'ilike', $term)->orWhere('email', 'ilike', $term);
                });
            })
            ->orderBy('name')
            ->paginate((int) $request->integer('per_page', 20));

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', PasswordPolicy::rule()],
            'is_active' => ['sometimes', 'boolean'],
            'two_factor_enabled' => ['sometimes', 'boolean'],
            'payer_id' => ['nullable', 'exists:payers,id'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'is_active' => $data['is_active'] ?? true,
            'two_factor_enabled' => $data['two_factor_enabled'] ?? false,
            'payer_id' => $data['payer_id'] ?? null,
            'email_verified_at' => now(),
        ]);
        $user->roles()->sync($data['role_ids']);

        return response()->json([
            'message' => 'User created.',
            'user' => $user->load('roles:id,name,slug'),
            'password_policy' => PasswordPolicy::meta(),
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', PasswordPolicy::rule()],
            'is_active' => ['sometimes', 'boolean'],
            'two_factor_enabled' => ['sometimes', 'boolean'],
            'payer_id' => ['nullable', 'exists:payers,id'],
            'role_ids' => ['sometimes', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ]);

        if (array_key_exists('password', $data) && $data['password']) {
            $user->password = Hash::make($data['password']);
        }
        foreach (['name', 'email', 'phone', 'is_active', 'two_factor_enabled', 'payer_id'] as $field) {
            if (array_key_exists($field, $data)) {
                $user->{$field} = $data[$field];
            }
        }
        $user->save();

        if (isset($data['role_ids'])) {
            $user->roles()->sync($data['role_ids']);
        }

        return response()->json([
            'message' => 'User updated.',
            'user' => $user->fresh()->load('roles:id,name,slug'),
        ]);
    }
}
