<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('period', 7)->unique(); // YYYY-MM
            $table->string('status', 20)->default('COMPLETED'); // RUNNING | COMPLETED | FAILED
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('accounts_processed')->default(0);
            $table->unsignedInteger('bills_generated')->default(0);
            $table->unsignedInteger('bills_held')->default(0);
            $table->unsignedInteger('exceptions_count')->default(0);
            $table->json('exception_report')->nullable();
            $table->json('summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_cycles');
    }
};
