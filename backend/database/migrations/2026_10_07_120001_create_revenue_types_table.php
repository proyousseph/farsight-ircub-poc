<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revenue_types', function (Blueprint $table) {
            $table->id();
            $table->string('revenue_code', 50)->unique();
            $table->string('name');
            $table->string('category', 20); // TAX | WATER
            $table->string('gl_code', 50);
            $table->decimal('default_rate', 15, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_types');
    }
};
