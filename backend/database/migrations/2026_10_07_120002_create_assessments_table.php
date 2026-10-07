<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payer_id')->constrained('payers')->cascadeOnDelete();
            $table->string('revenue_code', 50)->index();
            $table->string('control_number', 60)->unique();
            $table->decimal('amount_due', 15, 2);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('penalty_amount', 15, 2)->default(0);
            $table->date('due_date');
            $table->string('status', 20)->default('OPEN'); // OPEN | PART_PAID | PAID | REVERSED
            $table->string('period', 20)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['payer_id', 'status']);
            $table->index(['revenue_code', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
