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
use Illuminate\Validation\ValidationException;

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

        $this->assertAssignableRoles($request->user(), $data['role_ids']);

        // 2FA can only be enabled after the user confirms a TOTP secret via /auth/2fa/*.
        if (! empty($data['two_factor_enabled'])) {
            throw ValidationException::withMessages([
                'two_factor_enabled' => 'Enable 2FA only after the user completes TOTP setup and confirmation.',
            ]);
        }

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'is_active' => $data['is_active'] ?? true,
            'must_change_password' => true,
            'two_factor_enabled' => false,
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
            'admin_password' => ['nullable', 'string'],
            'payer_id' => ['nullable', 'exists:payers,id'],
            'role_ids' => ['sometimes', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ]);

        // Hosted demo: block password / deactivation / role changes on @ircub.test.
        if (\App\Support\DemoAccounts::skipPasswordChange() && \App\Support\DemoAccounts::isProtectedUser($user)) {
            $locking = array_key_exists('password', $data) && filled($data['password'])
                || (array_key_exists('is_active', $data) && ! $data['is_active'])
                || isset($data['role_ids'])
                || (array_key_exists('two_factor_enabled', $data) && ! $data['two_factor_enabled']);
            if ($locking) {
                \App\Support\DemoAccounts::assertMutable($user);
            }
        }

        if (isset($data['role_ids'])) {
            $this->assertAssignableRoles($request->user(), $data['role_ids']);
        }

        $wasActive = (bool) $user->is_active;
        $originalPayerId = $user->payer_id;
        $clearingTwoFactor = array_key_exists('two_factor_enabled', $data)
            && ! $data['two_factor_enabled']
            && ($user->two_factor_enabled || $user->two_factor_secret || $user->two_factor_confirmed_at);

        if ($clearingTwoFactor) {
            $actor = $request->user();
            if (! $actor || empty($data['admin_password']) || ! Hash::check($data['admin_password'], $actor->password)) {
                throw ValidationException::withMessages([
                    'admin_password' => 'Your password is required to disable another user\'s two-factor authentication.',
                ]);
            }
        }

        if (array_key_exists('two_factor_enabled', $data) && $data['two_factor_enabled'] && ! $user->two_factor_confirmed_at) {
            throw ValidationException::withMessages([
                'two_factor_enabled' => 'User must confirm TOTP setup before 2FA can be enabled.',
            ]);
        }

        if (array_key_exists('password', $data) && $data['password']) {
            $user->password = Hash::make($data['password']);
            $user->must_change_password = true;
            $user->tokens()->delete();
        }
        foreach (['name', 'email', 'phone', 'is_active', 'two_factor_enabled', 'payer_id'] as $field) {
            if (array_key_exists($field, $data)) {
                $user->{$field} = $data[$field];
            }
        }
        if ($clearingTwoFactor) {
            $user->two_factor_secret = null;
            $user->two_factor_confirmed_at = null;
            $user->two_factor_enabled = false;
        }
        $user->save();

        $rolesChanged = false;
        if (isset($data['role_ids'])) {
            $user->roles()->sync($data['role_ids']);
            $rolesChanged = true;
        }

        $payerChanged = array_key_exists('payer_id', $data)
            && (int) ($originalPayerId ?? 0) !== (int) ($data['payer_id'] ?? 0);

        if ($rolesChanged || $payerChanged || $clearingTwoFactor
            || ($wasActive && array_key_exists('is_active', $data) && ! $data['is_active'])) {
            $user->tokens()->delete();
        }

        return response()->json([
            'message' => 'User updated.',
            'user' => $user->fresh()->load('roles:id,name,slug'),
        ]);
    }

    /**
     * @param  list<int>  $roleIds
     */
    private function assertAssignableRoles(?User $actor, array $roleIds): void
    {
        if (! $actor) {
            throw ValidationException::withMessages(['role_ids' => 'Authenticated user required.']);
        }

        $roles = Role::query()->whereIn('id', $roleIds)->with('permissions')->get();

        if ($roles->contains(fn (Role $role) => $role->slug === 'system-administrator')
            && ! $actor->hasRole('system-administrator')) {
            throw ValidationException::withMessages([
                'role_ids' => 'Only a system administrator can assign the system-administrator role.',
            ]);
        }

        if ($actor->hasRole('system-administrator')) {
            return;
        }

        $actorPermissions = $actor->permissions();
        foreach ($roles as $role) {
            $roleSlugs = $role->permissions->pluck('slug')->all();
            $extra = array_values(array_diff($roleSlugs, $actorPermissions));
            if ($extra !== []) {
                throw ValidationException::withMessages([
                    'role_ids' => 'You cannot assign a role that grants permissions you do not hold.',
                ]);
            }
        }
    }
}
