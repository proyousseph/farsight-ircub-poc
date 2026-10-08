<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('prev_hash', 64)->nullable()->after('ip_address');
            $table->string('entry_hash', 64)->nullable()->after('prev_hash');
            $table->index('entry_hash');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['entry_hash']);
            $table->dropColumn(['prev_hash', 'entry_hash']);
        });
    }
};
