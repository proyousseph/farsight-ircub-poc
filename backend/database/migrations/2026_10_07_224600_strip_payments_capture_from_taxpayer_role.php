<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sync existing databases with the hardened taxpayer role (no payments.capture).
 */
return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->where('slug', 'taxpayer-customer')->value('id');
        $permissionId = DB::table('permissions')->where('slug', 'payments.capture')->value('id');

        if (! $roleId || ! $permissionId) {
            return;
        }

        DB::table('permission_role')
            ->where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->delete();
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('slug', 'taxpayer-customer')->value('id');
        $permissionId = DB::table('permissions')->where('slug', 'payments.capture')->value('id');

        if (! $roleId || ! $permissionId) {
            return;
        }

        $exists = DB::table('permission_role')
            ->where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->exists();

        if (! $exists) {
            DB::table('permission_role')->insert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
