<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Users & roles
            ['name' => 'Manage users', 'slug' => 'users.manage', 'module' => 'users'],
            ['name' => 'Manage roles', 'slug' => 'roles.manage', 'module' => 'users'],
            ['name' => 'Manage system config', 'slug' => 'config.manage', 'module' => 'config'],

            // Registry
            ['name' => 'Register payers', 'slug' => 'payers.create', 'module' => 'registry'],
            ['name' => 'View payers', 'slug' => 'payers.view', 'module' => 'registry'],
            ['name' => 'View own profile', 'slug' => 'payers.view_own', 'module' => 'registry'],

            // Revenue
            ['name' => 'Manage revenue types', 'slug' => 'revenue_types.manage', 'module' => 'revenue'],
            ['name' => 'Create assessments', 'slug' => 'assessments.create', 'module' => 'revenue'],
            ['name' => 'View assessments', 'slug' => 'assessments.view', 'module' => 'revenue'],
            ['name' => 'View own assessments', 'slug' => 'assessments.view_own', 'module' => 'revenue'],
            ['name' => 'Capture payments', 'slug' => 'payments.capture', 'module' => 'revenue'],
            ['name' => 'View payments', 'slug' => 'payments.view', 'module' => 'revenue'],
            ['name' => 'View own payments', 'slug' => 'payments.view_own', 'module' => 'revenue'],
            ['name' => 'Approve reversals', 'slug' => 'payments.approve_reversal', 'module' => 'revenue'],
            ['name' => 'Request reversals', 'slug' => 'payments.request_reversal', 'module' => 'revenue'],

            // Water
            ['name' => 'Capture meter readings', 'slug' => 'meters.capture', 'module' => 'water'],
            ['name' => 'Run billing cycles', 'slug' => 'billing.run', 'module' => 'water'],
            ['name' => 'View water bills', 'slug' => 'bills.view', 'module' => 'water'],
            ['name' => 'View own water bills', 'slug' => 'bills.view_own', 'module' => 'water'],

            // Channels
            ['name' => 'Run channel reconciliation', 'slug' => 'channels.reconcile', 'module' => 'channels'],

            // Audit / reports / FMIS
            ['name' => 'View audit logs', 'slug' => 'audit.view', 'module' => 'audit'],
            ['name' => 'View reports', 'slug' => 'reports.view', 'module' => 'reports'],
            ['name' => 'View dashboard', 'slug' => 'dashboard.view', 'module' => 'dashboard'],
            ['name' => 'Post to FMIS', 'slug' => 'fmis.post', 'module' => 'fmis'],
            ['name' => 'View FMIS reconciliation', 'slug' => 'fmis.reconcile', 'module' => 'fmis'],
        ];

        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(
                ['slug' => $permission['slug']],
                $permission
            );
        }

        $roleMap = [
            'system-administrator' => [
                'name' => 'System Administrator',
                'description' => 'Full system configuration and user administration.',
                'permissions' => Permission::query()->pluck('slug')->all(),
            ],
            'revenue-supervisor' => [
                'name' => 'Revenue Supervisor',
                'description' => 'View, approve and reverse revenue transactions (includes officer capabilities).',
                'permissions' => [
                    // Officer capabilities (child role is a subset)
                    'payers.create',
                    'payers.view',
                    'assessments.create',
                    'assessments.view',
                    'payments.capture',
                    'payments.view',
                    'payments.request_reversal',
                    'dashboard.view',
                    // Supervisor-only
                    'payments.approve_reversal',
                    'revenue_types.manage',
                    'reports.view',
                    'channels.reconcile',
                    'fmis.post',
                    'fmis.reconcile',
                    'audit.view',
                ],
            ],
            'revenue-officer' => [
                'name' => 'Revenue Officer',
                'description' => 'Register payers, create assessments and capture payments.',
                'permissions' => [
                    'payers.create',
                    'payers.view',
                    'assessments.create',
                    'assessments.view',
                    'payments.capture',
                    'payments.view',
                    'payments.request_reversal',
                    'dashboard.view',
                ],
            ],
            'water-billing-officer' => [
                'name' => 'Water Billing Officer',
                'description' => 'Capture meter readings and run billing cycles.',
                'permissions' => [
                    'payers.view',
                    'meters.capture',
                    'billing.run',
                    'bills.view',
                    'payments.view',
                    'dashboard.view',
                ],
            ],
            'auditor' => [
                'name' => 'Auditor',
                'description' => 'View-only access to transactions, audit logs and reports.',
                'permissions' => [
                    'payers.view',
                    'assessments.view',
                    'payments.view',
                    'bills.view',
                    'audit.view',
                    'reports.view',
                    'dashboard.view',
                    'channels.reconcile',
                    'fmis.reconcile',
                ],
            ],
            'taxpayer-customer' => [
                'name' => 'Taxpayer / Customer',
                'description' => 'Self-service access to own bills, assessments and payments.',
                'permissions' => [
                    'payers.view_own',
                    'assessments.view_own',
                    'payments.view_own',
                    'bills.view_own',
                ],
            ],
        ];

        // Create/update roles first (no parents), then wire hierarchy.
        foreach ($roleMap as $slug => $data) {
            /** @var Role $role */
            $role = Role::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'is_system' => true,
                    'is_active' => true,
                ]
            );

            $permissionIds = Permission::query()
                ->whereIn('slug', $data['permissions'])
                ->pluck('id');

            $role->permissions()->sync($permissionIds);
        }

        // Hierarchical tree (brief: hierarchical role system):
        // System Administrator
        //   ├─ Revenue Supervisor
        //   │    └─ Revenue Officer
        //   ├─ Water Billing Officer
        //   ├─ Auditor
        //   └─ Taxpayer / Customer
        $adminId = Role::query()->where('slug', 'system-administrator')->value('id');
        $supervisorId = Role::query()->where('slug', 'revenue-supervisor')->value('id');

        $hierarchy = [
            'system-administrator' => ['parent' => null, 'level' => 0],
            'revenue-supervisor' => ['parent' => $adminId, 'level' => 1],
            'revenue-officer' => ['parent' => $supervisorId, 'level' => 2],
            'water-billing-officer' => ['parent' => $adminId, 'level' => 1],
            'auditor' => ['parent' => $adminId, 'level' => 1],
            'taxpayer-customer' => ['parent' => $adminId, 'level' => 1],
        ];

        foreach ($hierarchy as $slug => $meta) {
            Role::query()->where('slug', $slug)->update([
                'parent_id' => $meta['parent'],
                'level' => $meta['level'],
            ]);
        }

        app(\App\Services\SystemConfigService::class)->ensureSeeded();

        $demoUsers = [
            [
                'name' => 'System Administrator',
                'email' => 'admin@ircub.test',
                'role' => 'system-administrator',
            ],
            [
                'name' => 'Revenue Supervisor',
                'email' => 'supervisor@ircub.test',
                'role' => 'revenue-supervisor',
            ],
            [
                'name' => 'Revenue Officer',
                'email' => 'officer@ircub.test',
                'role' => 'revenue-officer',
            ],
            [
                'name' => 'Water Billing Officer',
                'email' => 'water@ircub.test',
                'role' => 'water-billing-officer',
            ],
            [
                'name' => 'Auditor User',
                'email' => 'auditor@ircub.test',
                'role' => 'auditor',
            ],
            [
                'name' => 'Demo Taxpayer',
                'email' => 'taxpayer@ircub.test',
                'role' => 'taxpayer-customer',
            ],
        ];

        $phoneSeq = 6100001;
        foreach ($demoUsers as $demo) {
            /** @var User $user */
            $user = User::query()->firstOrCreate(
                ['email' => $demo['email']],
                [
                    'name' => $demo['name'],
                    // Deterministic phones — no Faker (unavailable in --no-dev production images).
                    'phone' => '252'.(string) $phoneSeq++,
                    'password' => Hash::make('Password@123'),
                    'is_active' => true,
                    // Shared demo password — force rotation everywhere except automated tests.
                    'must_change_password' => ! app()->environment('testing'),
                    'email_verified_at' => now(),
                ]
            );

            // Re-seed must not reset passwords (preserves post-change credentials across boots).
            if (! $user->wasRecentlyCreated) {
                $user->forceFill([
                    'name' => $demo['name'],
                    'is_active' => true,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();
            }

            $roleId = Role::query()->where('slug', $demo['role'])->value('id');
            $user->roles()->sync([$roleId]);
        }
    }
}
