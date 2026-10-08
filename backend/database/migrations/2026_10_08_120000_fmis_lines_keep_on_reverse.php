<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fmis_journal_lines', function (Blueprint $table) {
            $table->dropUnique(['payment_id']);
        });

        Schema::table('fmis_journal_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('source_payment_id')->nullable()->after('payment_id');
            $table->timestamp('reversed_at')->nullable()->after('fmis_line_ref');
            $table->index('payment_id');
            $table->index('source_payment_id');
        });

        // Active (non-reversed) lines still enforce one open posting per payment.
        // Partial unique indexes are Postgres-only; app layer also checks on create.
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                'CREATE UNIQUE INDEX fmis_journal_lines_payment_id_active_unique
                 ON fmis_journal_lines (payment_id)
                 WHERE payment_id IS NOT NULL AND reversed_at IS NULL'
            );
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                'DROP INDEX IF EXISTS fmis_journal_lines_payment_id_active_unique'
            );
        }

        Schema::table('fmis_journal_lines', function (Blueprint $table) {
            $table->dropIndex(['payment_id']);
            $table->dropIndex(['source_payment_id']);
            $table->dropColumn(['source_payment_id', 'reversed_at']);
            $table->unique('payment_id');
        });
    }
};
