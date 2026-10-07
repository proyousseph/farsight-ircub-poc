<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payer_id')->constrained('payers')->cascadeOnDelete();
            $table->foreignId('assessment_id')->nullable()->constrained('assessments')->nullOnDelete();
            $table->foreignId('water_bill_id')->nullable()->constrained('water_bills')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('revenue_code', 50);
            $table->string('channel', 30); // BANK | MOBILE_MONEY
            $table->decimal('amount_usd', 15, 2);
            $table->decimal('amount_local', 15, 2)->nullable();
            $table->string('local_currency', 10)->nullable();
            $table->decimal('fx_rate', 18, 6)->nullable();
            $table->foreignId('exchange_rate_id')->nullable()->constrained('exchange_rates')->nullOnDelete();
            $table->string('external_ref', 100)->unique();
            $table->string('provider_txn_id', 100)->nullable()->index();
            $table->string('status', 30)->default('INITIATED');
            // INITIATED | PENDING | SUCCESS | FAILED | PERMANENTLY_FAILED
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->unsignedTinyInteger('max_retries')->default(3);
            $table->timestamp('last_status_check_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->boolean('callback_verified')->default(false);
            $table->json('initiate_payload')->nullable();
            $table->json('callback_payload')->nullable();
            $table->json('status_history')->nullable();
            $table->timestamp('supervisor_notified_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'next_retry_at']);
            $table->index(['channel', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_payments');
    }
};
