<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payer_obligations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payer_id')->constrained('payers')->cascadeOnDelete();
            $table->string('revenue_code', 50);
            $table->string('name');
            $table->string('category', 20)->default('TAX'); // TAX | WATER
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['payer_id', 'revenue_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payer_obligations');
    }
};
