<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payers', function (Blueprint $table) {
            $table->id();
            $table->string('payer_type', 20); // INDIVIDUAL | BUSINESS
            $table->string('tin', 50)->unique();
            $table->string('full_name');
            $table->string('national_id', 50)->nullable()->index();
            $table->string('phone', 30)->index();
            $table->string('email')->nullable()->index();
            $table->string('address')->nullable();
            $table->string('status', 20)->default('ACTIVE'); // ACTIVE | INACTIVE | FLAGGED
            $table->boolean('duplicate_flagged')->default(false)->index();
            $table->text('duplicate_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payers');
    }
};
