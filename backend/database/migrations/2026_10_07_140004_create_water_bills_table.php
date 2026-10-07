<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_cycle_id')->constrained('billing_cycles')->cascadeOnDelete();
            $table->foreignId('water_account_id')->constrained('water_accounts')->cascadeOnDelete();
            $table->foreignId('payer_id')->constrained('payers')->cascadeOnDelete();
            $table->foreignId('meter_reading_id')->nullable()->constrained('meter_readings')->nullOnDelete();
            $table->string('bill_number', 40)->unique();
            $table->string('period', 7);
            $table->decimal('previous_reading', 14, 3)->default(0);
            $table->decimal('current_reading', 14, 3)->default(0);
            $table->decimal('consumption', 14, 3)->default(0);
            $table->decimal('tariff_amount', 12, 2)->default(0);
            $table->decimal('fixed_charge', 12, 2)->default(0);
            $table->decimal('arrears_brought_forward', 12, 2)->default(0);
            $table->decimal('payments_applied', 12, 2)->default(0);
            $table->decimal('total_due', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->date('due_date');
            $table->string('status', 20)->default('HELD'); // HELD | RELEASED | PART_PAID | PAID
            $table->boolean('abnormal_flag')->default(false);
            $table->string('abnormal_reason')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('notification_status', 20)->default('PENDING'); // PENDING | SENT | FAILED | SKIPPED
            $table->json('notification_log')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['water_account_id', 'period']);
            $table->index(['payer_id', 'status']);
            $table->index(['period', 'abnormal_flag']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_bills');
    }
};
