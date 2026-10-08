<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $now = now();
        $permId = DB::table('permissions')->where('slug', 'payments.pay_own')->value('id');
        if (! $permId) {
            $permId = DB::table('permissions')->insertGetId([
                'name' => 'Pay own obligations',
                'slug' => 'payments.pay_own',
                'module' => 'revenue',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $roleId = DB::table('roles')->where('slug', 'taxpayer-customer')->value('id');
        if ($roleId && $permId) {
            $exists = DB::table('permission_role')
                ->where('permission_id', $permId)
                ->where('role_id', $roleId)
                ->exists();
            if (! $exists) {
                DB::table('permission_role')->insert([
                    'permission_id' => $permId,
                    'role_id' => $roleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permId = DB::table('permissions')->where('slug', 'payments.pay_own')->value('id');
        if (! $permId) {
            return;
        }
        DB::table('permission_role')->where('permission_id', $permId)->delete();
        DB::table('permissions')->where('id', $permId)->delete();
    }
};
