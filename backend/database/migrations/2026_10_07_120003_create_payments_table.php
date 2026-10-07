<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payer_id')->constrained('payers')->cascadeOnDelete();
            $table->foreignId('assessment_id')->nullable()->constrained('assessments')->nullOnDelete();
            $table->string('revenue_code', 50)->index();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 10)->default('USD');
            $table->string('channel', 30); // BANK | MOBILE_MONEY | CASH
            $table->string('external_ref', 100)->unique();
            $table->timestamp('paid_at');
            $table->string('status', 20)->default('SUCCESS'); // SUCCESS | FAILED | REVERSED
            $table->string('fmis_status', 20)->default('PENDING'); // PENDING | POSTED | FAILED
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['payer_id', 'status']);
            $table->index(['channel', 'paid_at']);
            $table->index(['status', 'fmis_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
