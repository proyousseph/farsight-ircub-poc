<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('payer_id')->nullable()->after('id')->constrained('payers')->nullOnDelete();
            $table->boolean('two_factor_enabled')->default(false)->after('must_change_password');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('reversal_status', 20)->nullable()->after('status'); // null | PENDING | REJECTED
            $table->text('reversal_reason')->nullable()->after('reversal_status');
            $table->foreignId('reversal_requested_by')->nullable()->after('reversal_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('reversal_requested_at')->nullable()->after('reversal_requested_by');
            $table->foreignId('reversal_reviewed_by')->nullable()->after('reversal_requested_at')->constrained('users')->nullOnDelete();
            $table->timestamp('reversal_reviewed_at')->nullable()->after('reversal_reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reversal_requested_by');
            $table->dropConstrainedForeignId('reversal_reviewed_by');
            $table->dropColumn([
                'reversal_status',
                'reversal_reason',
                'reversal_requested_at',
                'reversal_reviewed_at',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payer_id');
            $table->dropColumn('two_factor_enabled');
        });
    }
};
