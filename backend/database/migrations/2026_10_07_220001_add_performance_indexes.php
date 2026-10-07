<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['paid_at', 'status'], 'payments_paid_at_status_idx');
            $table->index(['revenue_code', 'paid_at'], 'payments_revenue_paid_at_idx');
            $table->index(['status', 'paid_at'], 'payments_status_paid_at_idx');
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->index(['status', 'due_date'], 'assessments_status_due_date_idx');
            $table->index(['created_at'], 'assessments_created_at_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['created_at'], 'audit_logs_created_at_idx');
            $table->index(['user_id', 'created_at'], 'audit_logs_user_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_paid_at_status_idx');
            $table->dropIndex('payments_revenue_paid_at_idx');
            $table->dropIndex('payments_status_paid_at_idx');
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->dropIndex('assessments_status_due_date_idx');
            $table->dropIndex('assessments_created_at_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_created_at_idx');
            $table->dropIndex('audit_logs_user_created_at_idx');
        });
    }
};
