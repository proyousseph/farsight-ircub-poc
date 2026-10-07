<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meter_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('water_account_id')->constrained('water_accounts')->cascadeOnDelete();
            $table->date('reading_date');
            $table->decimal('reading_value', 14, 3);
            $table->decimal('previous_reading', 14, 3)->nullable();
            $table->decimal('consumption', 14, 3)->default(0);
            $table->boolean('is_rollover')->default(false);
            $table->boolean('is_meter_replacement')->default(false);
            $table->string('status', 20)->default('ACCEPTED'); // ACCEPTED | REJECTED
            $table->string('period', 7)->nullable(); // YYYY-MM
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['water_account_id', 'reading_date']);
            $table->index(['period', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meter_readings');
    }
};
